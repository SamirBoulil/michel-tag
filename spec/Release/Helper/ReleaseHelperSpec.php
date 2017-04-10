<?php

namespace spec\Release\Helper;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Release\Helper\FileUpdater;
use Release\Helper\PullRequestHelper;
use Release\Helper\ReleaseHelper;
use Release\Helper\RepositoryHelper;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class ReleaseHelperSpec extends ObjectBehavior
{
    function let(
        SymfonyStyle $io,
        Filesystem $fs,
        RepositoryHelper $repositoryHelper,
        FileUpdater $fileUpdater,
        PullRequestHelper $pullRequestHelper
    ) {
        $this->beConstructedWith($io, $fs, $repositoryHelper, $fileUpdater, $pullRequestHelper);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(ReleaseHelper::class);
    }
}
