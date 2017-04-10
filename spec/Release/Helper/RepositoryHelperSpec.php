<?php

namespace spec\Release\Helper;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Release\Helper\RepositoryHelper;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class RepositoryHelperSpec extends ObjectBehavior
{
    function let(SymfonyStyle $io, Filesystem $fs)
    {
        $this->beConstructedWith($io, $fs);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(RepositoryHelper::class);
    }
}
