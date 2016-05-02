<?php
/**
 * Copyright (c) 2015 Arthur Islamov
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace JJs\Bundle\GeonamesBundle\Data;

use JJs\Bundle\GeonamesBundle\Entity\City;
use JJs\Bundle\GeonamesBundle\Entity\CityRepository;
use JJs\Bundle\GeonamesBundle\Entity\CountryRepository;
use JJs\Bundle\GeonamesBundle\Entity\StateRepository;
use JJs\Bundle\GeonamesBundle\Model\CountryRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Loads Countries from GeoNames.org
 *
 * @author Josiah <josiah@jjs.id.au>
 */
class AlternativeNamesLoader
{
    /**
     * Default URL
     *
     * Default url where countries should be loaded from.
     *
     * @var string
     */
    const DEFAULT_FILE = 'http://download.geonames.org/export/dump/alternateNames.zip';

    /**
     * AltName ID
     *
     * @var int
     */
    const COLUMN_ALTNAME_ID = 0;

    /**
     * GeoName ID position
     *
     * @var int
     */
    const COLUMN_GEONAME_ID = 1;

    /**
     * Language code
     *
     * @var int
     */
    const COLUMN_LANG_CODE = 2;

    /**
     * Alternative name text
     *
     * @var int
     */
    const COLUMN_ALTNAME = 3;

    /**
     * Country repository
     * 
     * @var CountryRepositoryInterface
     */
    protected $countryRepository;
    protected $stateRepository;
    protected $cityRepository;


    /**
     * Creates a new altnames loader
     *
     */
    public function __construct(CountryRepositoryInterface $countryRepository, StateRepository $stateRepository,
                                CityRepository $cityRepository)
    {
        $this->countryRepository = $countryRepository;
        $this->stateRepository = $stateRepository;
        $this->cityRepository = $cityRepository;
    }

    /**
     * Loads the default file into the database
     * 
     * @param string $file File to load (URL)
     * 
     * @return void
     */
    public function load($lang, $file = null, LoggerInterface $log = null)
    {
        $file = $file ?: static::DEFAULT_FILE;
        $log  = $log ?: new NullLogger();
        
        // Open the tab separated file for reading
        $tsv = fopen($file, 'r');
        while(false !== $data = fgetcsv($tsv, 0, "\t")) {

            // Skip all commented codes
            if (substr($data[0], 0, 1) === '#') continue;

            $geonameId        = $data[self::COLUMN_GEONAME_ID];
            $localityLang     = $data[self::COLUMN_LANG_CODE];
            $altname          = $data[self::COLUMN_ALTNAME];

            if($lang != $localityLang)
                continue;

            // we don't know what locality is it, so we will try all

            /** @var City $locality */
            $locality = $this->cityRepository->findOneByGeonameIdentifier($geonameId);
            if($locality){
                $locality->setTranslatableLocale($lang);
                $locality->setNameUtf8($altname);
                $this->cityRepository->persistLocality($locality);
                continue;
            }

            $locality = $this->stateRepository->findOneByGeonameIdentifier($geonameId);
            if($locality){
                $locality->setTranslatableLocale($lang);
                $locality->setNameUtf8($altname);
                $this->stateRepository->persistLocality($locality);
                continue;
            }

            $locality = $this->countryRepository->findOneByGeonameIdentifier($geonameId);
            if($locality) {
                $locality->setTranslatableLocale($lang);
                $locality->setName($altname);
                $this->countryRepository->saveCountry($locality);
                continue;
            }
        }
    }
}