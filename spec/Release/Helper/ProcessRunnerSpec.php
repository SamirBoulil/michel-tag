<?php

namespace spec\Release\Helper;

use Release\Helper\ProcessRunner;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ProcessRunnerSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(ProcessRunner::class);
    }
}
