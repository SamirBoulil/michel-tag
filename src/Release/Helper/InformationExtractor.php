<?php

namespace Release\Helper;

class InformationExtractor
{
    const PRODUCT = 'product';
    const EDITION = 'edition';
    const DISTRIBUTION = 'distribution';

    /**
     * Extract repositoryInformations
     *
     * @param string $repository
     *
     * @return array
     */
    public static function extractRepositoryInformation($repository)
    {
        preg_match('/^(?P<product>.*)-(?P<edition>.*)-(?P<distribution>.*)$/', $repository, $matches);

        return [
            self::PRODUCT => $matches['product'],
            self::EDITION => $matches['edition'],
            self::DISTRIBUTION => $matches['distribution'],
        ];
    }
}
