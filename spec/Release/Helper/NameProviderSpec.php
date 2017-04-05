<?php

namespace spec\Release\Helper;

use Github\Api\Repo;
use Github\Client;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Release\Helper\NameProvider;
use Symfony\Component\Console\Style\SymfonyStyle;

class NameProviderSpec extends ObjectBehavior
{
    function let(SymfonyStyle $io, Client $client)
    {
        $this->beConstructedWith($io, $client);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(NameProvider::class);
    }

    function it_provides_a_tag_name_based_on_the_provided_branch_if_user_accepts_it($client, $io, Repo $repo)
    {
        $client->api('repo')->willReturn($repo);
        $repo->tags('akeneo', 'pim-community-dev')->willReturn([
            ['name' => 'v1.7.0'],
            ['name' => 'v1.7.1'],
            ['name' => 'v1.7.0-ALPHA']
        ]);
        $io->confirm(Argument::any(), true)->willReturn(true);

        $this->getTagName('akeneo', 'pim-community-dev', '1.7')->shouldReturn('1.7.2');
    }

    function it_provides_a_tag_name_based_on_the_user_entry_if_user_refuse_the_suggested_one($client, $io, Repo $repo)
    {
        $client->api('repo')->willReturn($repo);
        $repo->tags('akeneo', 'pim-community-dev')->willReturn([
            ['name' => 'v1.7.0'],
            ['name' => 'v1.7.1'],
            ['name' => 'v1.7.0-ALPHA']
        ]);
        $io->confirm(Argument::any(), true)->willReturn(false);

        $io->ask(Argument::any())->willReturn('1.7.3');

        $this->getTagName('akeneo', 'pim-community-dev', '1.7')->shouldReturn('1.7.3');
    }

    function it_asks_the_user_if_there_is_no_valid_tags($client, $io, Repo $repo)
    {
        $client->api('repo')->willReturn($repo);
        $repo->tags('akeneo', 'pim-community-dev')->willReturn([
            ['name' => 'v1.7.a'],
            ['name' => 'v1.7.a'],
            ['name' => 'v1.7.a-ALPHA']
        ]);
        $io->confirm(Argument::any(), true)->shouldNotBeCalled();

        $io->ask(Argument::any())->willReturn('1.7.12');

        $this->getTagName('akeneo', 'pim-community-dev', '1.7')->shouldReturn('1.7.12');
    }

    function it_asks_the_user_if_there_is_no_tags_for_the_branch($client, $io, Repo $repo)
    {
        $client->api('repo')->willReturn($repo);
        $repo->tags('akeneo', 'pim-community-dev')->willReturn([]);
        $io->confirm(Argument::any(), true)->shouldNotBeCalled();

        $io->ask(Argument::any())->willReturn('1.7.5');

        $this->getTagName('akeneo', 'pim-community-dev', '1.7')->shouldReturn('1.7.5');
    }
}
