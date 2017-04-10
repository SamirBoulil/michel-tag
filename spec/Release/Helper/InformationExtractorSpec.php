<?php

namespace spec\Release\Helper;

use Release\Helper\InformationExtractor;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class InformationExtractorSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(InformationExtractor::class);
    }

    function it_extract_repository_information()
    {
        $this->extractRepositoryInformation('pim-community-dev')->shouldReturn([
            'product' => 'pim',
            'edition' => 'community',
            'version' => 'dev'
        ]);
        $this->extractRepositoryInformation('pim-enterprise-standard')->shouldReturn([
            'product' => 'pim',
            'edition' => 'enterprise',
            'version' => 'standard'
        ]);
        $this->extractRepositoryInformation('pam-community-standard')->shouldReturn([
            'product' => 'pam',
            'edition' => 'community',
            'version' => 'standard'
        ]);
    }
}
