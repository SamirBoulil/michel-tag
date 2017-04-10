<?php

namespace Release\Helper;

use Github\Client;
use Github\Exception\RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;

class NameProvider
{
    protected $io;
    protected $client;

    /**
     * @param SymfonyStyle $io
     * @param Client       $this->client
     */
    public function __construct(SymfonyStyle $io, Client $client)
    {
        $this->io     = $io;
        $this->client = $client;
    }

    /**
     * Get a tag name for the next release
     *
     * @param string $owner
     * @param string $repository
     * @param string $branch
     *
     * @return string
     */
    public function getTagName($owner, $repository, $branch)
    {
        //We fetch all the tags from the repo and filter the with the branch name
        $tags = array_values(array_filter($this->client->api('repo')->tags($owner, $repository), function ($candidate) use ($branch) {
            return 0 === strpos($candidate['name'], 'v' . $branch);
        }));

        //We sort them
        $tags = array_map(function ($tag) {
            return substr($tag['name'], 1);
        }, $tags);
        usort($tags, 'version_compare');
        $tags = array_reverse($tags);

        if (0 === count($tags)) {
            //If there is no tag corresponding, we ask the user for one
            $tagName = $this->getTagFromUser(sprintf('There is no tag for the %s branch', $branch));
        } else {
            //We validate that the last published tag is valid
            $tag = $tags[0];
            $foundTag = preg_match('/^(?P<major>\d+).(?P<minor>\d+).(?P<patch>\d+).*$/', $tag, $matches);

            if (!$foundTag) {
                //If not we ask for the user
                $tagName = $this->getTagFromUser(sprintf('We where not able to guess the next tag for the %s branch', $branch));
            } else {
                //Else, we suggest a tag and validate it with the user
                $suggestedTagName = sprintf(
                    '%s.%s.%s',
                    $matches['major'],
                    $matches['minor'],
                    $matches['patch'] + 1
                );
                $tagNameValidated = $this->io->confirm(sprintf(
                    'Last tagged version was %s, we suggest %s as tag name for this release.' .
                    "\n" .
                    'Do you want to proceed with this name?',
                    $tag,
                    $suggestedTagName
                ), true);
                $tagName = $tagNameValidated ?
                    $suggestedTagName :
                    $this->getTagFromUser(sprintf('Ok, no problem', $branch));
            }
        }

        return $tagName;
    }

    /**
     * Ask the user for a tag name
     *
     * @param string $reason
     *
     * @return string
     */
    protected function getTagFromUser($reason)
    {
        do {
            $tagName = $this->io->ask(sprintf(
                '%s. ' .
                "\n" .
                'How do you want to name the tag? (example 1.7.3) ',
                $reason
            ));
        } while (null === $tagName);

        return $tagName;
    }
}
