<?php

namespace Release\Command;

use Github\Client;
use Release\Helper\FileUpdater;
use Release\Helper\NameProvider;
use Release\Helper\ParamChecker;
use Release\Helper\PullRequestHelper;
use Release\Helper\ReleaseHelper;
use Release\Helper\RepositoryHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class TagCommand extends Command
{
    const DEV_SUFFIX = '-dev';
    const STD_SUFFIX = '-standard';

    protected $config;

    public function __construct(array $config)
    {
        parent::__construct();

        $this->config = $config;
    }

    protected function configure()
    {
        $this->setName('michel:tag')
            ->setDescription('Release a new version of Akeneo PIM')
            ->setHelp('This command will run each steps of an akeneo PIM release')
            ->addArgument('branch', InputArgument::REQUIRED, 'Which branch do you want to release (example: 1.7)')
            ->addArgument('owner', InputArgument::OPTIONAL, 'Who is the owner of the repository', 'juliensnz')
            ->addArgument('community_repo', InputArgument::OPTIONAL, 'The name of the community repo', 'pim-community')
            ->addArgument('enterprise_repo', InputArgument::OPTIONAL, 'The name of the enterprise repo', 'pim-enterprise');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $fs = new Filesystem();

        $io->text(file_get_contents(getcwd(). '/src/Release/resources/welcome.txt'));

        $io->ask('Are you ready?', 'Hell yeah!');

        $io->section('Check of access rights');

        $client = new Client();
        $client->authenticate($this->config['github_token'], Client::AUTH_URL_TOKEN);

        $owner          = $input->getArgument('owner');
        $communityRepo  = $input->getArgument('community_repo');
        $enterpriseRepo = $input->getArgument('enterprise_repo');
        $branch         = $input->getArgument('branch');

        $repositoryHelper = new RepositoryHelper($io, $fs);
        $fileUpdater = new FileUpdater($io, $fs, $client);
        $pullRequestHelper = new PullRequestHelper($io, $client);
        $releaseHelper = new ReleaseHelper($io, $fs, $repositoryHelper, $fileUpdater, $pullRequestHelper);
        $paramChecker = new ParamChecker($io, $client);
        $nameProvider = new NameProvider($io, $client);

        $ceDevRepository = $paramChecker->checkRepo($owner, $communityRepo . self::DEV_SUFFIX);
        $ceStdRepository = $paramChecker->checkRepo($owner, $communityRepo . self::STD_SUFFIX);
        $eeDevRepository = $paramChecker->checkRepo($owner, $enterpriseRepo . self::DEV_SUFFIX);
        $eeStdRepository = $paramChecker->checkRepo($owner, $enterpriseRepo . self::STD_SUFFIX);

        if (false === $ceDevRepository) {
            $io->error([
                'You don\'t have access to the community dev repository.',
                'Please check that it exists and that you can access it before running the release script.'
            ]);

            return 0;
        }

        $branchName = $paramChecker->checkBranch($owner, $ceDevRepository['name'], $branch);
        if (false === $branchName) {
            return 0;
        }

        $tagName = $nameProvider->getTagName($owner, $ceDevRepository['name'], $branchName);
        $releaseBranch = sprintf('%s_%s', str_replace('.', '_', $tagName), uniqid());
        $releaseFolder = sprintf('%s/releases/%s_%s', getcwd(), date('Y_m_d_G_i_s'), $releaseBranch);

        $io->note([
            'Ok so before doing the big jump, let\'s sum up:',
            sprintf('We will release a new version of the %s branch', $branchName),
            sprintf('This new version will be named v%s', $tagName),
            sprintf('To do so, we will create a branch named %s', $releaseBranch),
            sprintf('And use %s as a release folder', $releaseFolder),
        ]);
        if (!$io->confirm('Are you ready to continue?', true)) {
            return 0;
        }

        $releaseHelper->release($owner, $ceDevRepository['name'], $releaseFolder, $branchName, $releaseBranch, $tagName);
        $releaseHelper->release($owner, $ceStdRepository['name'], $releaseFolder, $branchName, $releaseBranch, $tagName);
        $releaseHelper->release($owner, $eeDevRepository['name'], $releaseFolder, $branchName, $releaseBranch, $tagName);
        $releaseHelper->release($owner, $eeStdRepository['name'], $releaseFolder, $branchName, $releaseBranch, $tagName);

        $io->section('After release tasks');
        preg_match('/^(?P<major>\d+).(?P<minor>\d+).(?P<patch>\d+).*$/', $tagName, $matches);
        $io->text([
            'Before leaving, remember that you still have work to do',
            'First we need to update the ref-envs (from ansible repository):',
            '',
            sprintf(
                '    ansible-playbook -i inventories/core.inventory -l ref-pim*-%s-%s*.akeneo.com pim-env.yml',
                $matches['major'],
                $matches['minor']
            ),
            '',
            'Then we need to update the public demo (if we tagged the last major version):',
            '',
            sprintf(
                '    ansible-playbook -i inventories/core.inventory -l demo-ref.akeneo.com pim-env.yml',
                $matches['major'],
                $matches['minor']
            ),
            '',
            'After that we will deploy the archive on the website (using dev-tools):',
            '',
            sprintf(
                '    ./deploy-archives.sh %s',
                $tagName
            ),
            '',
            'Last but not least, we update the clients mirrors:',
            '',
            sprintf('    curl -H "Content-Type: application/json" -X POST -d \'{"repository": {"name": "pim-enterprise-dev"}}\' "https://partners.akeneo.com/api/v1/hook-receiver/?api_key=%s"', $this->config['papo_token'])
        ]);


        $io->text(file_get_contents(getcwd(). '/src/Release/resources/done.txt'));
    }
}
