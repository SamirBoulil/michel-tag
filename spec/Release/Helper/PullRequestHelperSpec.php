<?php

namespace spec\Release\Helper;

use Github\Client;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Release\Helper\PullRequestHelper;
use Symfony\Component\Console\Style\SymfonyStyle;

class PullRequestHelperSpec extends ObjectBehavior
{
    function let(SymfonyStyle $io, Client $client)
    {
        $this->beConstructedWith($io, $client);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(PullRequestHelper::class);
    }
}
