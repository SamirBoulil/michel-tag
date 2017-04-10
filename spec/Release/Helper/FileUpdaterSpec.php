<?php

namespace spec\Release\Helper;

use Github\Client;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Release\Helper\FileUpdater;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class FileUpdaterSpec extends ObjectBehavior
{
    function let(SymfonyStyle $io, Filesystem $fs, Client $client)
    {
        $this->beConstructedWith($io, $fs, $client);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(FileUpdater::class);
    }
}
