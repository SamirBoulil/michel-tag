<?php

namespace spec\Release\Helper;

use Github\Client;
use Github\Api\Repo;
use Github\Exception\RuntimeException;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Release\Helper\ParamChecker;
use Symfony\Component\Console\Style\SymfonyStyle;

class ParamCheckerSpec extends ObjectBehavior
{
    function let(SymfonyStyle $io, Client $client)
    {
        $this->beConstructedWith($io, $client);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(ParamChecker::class);
    }

    function it_checks_that_the_repo_is_accessible_and_succeed($client, Repo $repo, $io)
    {
        $client->api('repo')->willReturn($repo);
        $repo->show('akeneo', 'pim-community-dev')->willReturn([
            'name' => 'pim-community-dev'
        ]);

        $io->success(Argument::any())->shouldBeCalled();

        $this->checkRepo('akeneo', 'pim-community-dev')->shouldReturn([
            'name' => 'pim-community-dev'
        ]);
    }

    function it_checks_that_the_repo_is_accessible_and_fail($client, Repo $repo, $io)
    {
        $client->api('repo')->willReturn($repo);
        $repo->show('akeneo', 'pim-community-dev')->willThrow(new RuntimeException());

        $io->error(Argument::any())->shouldBeCalled();

        $this->checkRepo('akeneo', 'pim-community-dev')->shouldReturn(false);
    }

    function it_checks_that_the_branch_is_valid_and_succeed($client, Repo $repo, $io)
    {
        $client->api('repo')->willReturn($repo);
        $repo->branches('akeneo', 'pim-community-dev')->willReturn([
            ['name' => '1.8'], ['name' => '1.7']]);

        $io->section(Argument::any())->shouldBeCalled();
        $io->success(Argument::any())->shouldBeCalled();

        $this->checkBranch('akeneo', 'pim-community-dev', '1.7')->shouldReturn('1.7');
    }

    function it_checks_that_the_branch_is_not_valid($client, Repo $repo, $io)
    {
        $client->api('repo')->willReturn($repo);
        $repo->branches('akeneo', 'pim-community-dev')->shouldNotBeCalled();

        $io->error(Argument::any())->shouldBeCalled();
        $io->section(Argument::any())->shouldBeCalled();
        $io->success(Argument::any())->shouldNotBeCalled();

        $this->checkBranch('akeneo', 'pim-community-dev', '12')->shouldReturn(false);
    }

    function it_checks_that_the_branch_doesnt_exists($client, Repo $repo, $io)
    {
        $client->api('repo')->willReturn($repo);
        $repo->branches('akeneo', 'pim-community-dev')->willReturn([
            ['name' => '1.8'], ['name' => '1.7']]);

        $io->error(Argument::any())->shouldBeCalled();
        $io->section(Argument::any())->shouldBeCalled();
        $io->success(Argument::any())->shouldBeCalled();

        $this->checkBranch('akeneo', 'pim-community-dev', '2.0')->shouldReturn(false);
    }
}
