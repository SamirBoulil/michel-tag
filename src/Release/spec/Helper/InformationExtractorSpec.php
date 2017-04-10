<?php

namespace spec\Release\Helper;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Release\Helper\InformationExtractor;

class InformationExtractorSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(InformationExtractor::class);
    }
}
