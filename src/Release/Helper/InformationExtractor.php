<?php

namespace Release\Helper;

class InformationExtractor
{
    const PRODUCT = 'product';
    const EDITION = 'edition';
    const VERSION = 'version';

    /**
     * Extract repositoryInformations
     *
     * @param string $repository
     *
     * @return array
     */
    public static function extractRepositoryInformation($repository)
    {
        preg_match('/^(?P<product>.*)-(?P<edition>.*)-(?P<version>.*)$/', $repository, $matches);

        return [
            self::PRODUCT => $matches['product'],
            self::EDITION => $matches['edition'],
            self::VERSION => $matches['version']
        ];
    }
}
