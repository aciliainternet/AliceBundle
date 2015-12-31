<?php

/*
 * This file is part of the Hautelook\AliceBundle package.
 *
 * (c) Baldur Rensch <brensch@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hautelook\AliceBundle\Doctrine\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\ManagerRegistry;
use Hautelook\AliceBundle\Alice\DataFixtures\Fixtures\LoaderInterface as FixturesLoaderInterface;
use Hautelook\AliceBundle\Alice\DataFixtures\LoaderInterface;
use Hautelook\AliceBundle\Doctrine\DataFixtures\Executor\FixturesExecutorInterface;
use Hautelook\AliceBundle\Doctrine\Finder\FixturesFinder;
use Hautelook\AliceBundle\Doctrine\Generator\LoaderGeneratorInterface;
use Hautelook\AliceBundle\Finder\FixturesFinderInterface;
use Hautelook\AliceBundle\Resolver\BundlesResolverInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Command used to load the fixtures.
 *
 * @author Théo FIDRY <theo.fidry@gmail.com>
 */
class LoadDataFixturesCommand extends ContainerAwareCommand
{
    /**
     * @var BundlesResolverInterface
     */
    private $bundlesResolver;

    /**
     * @var ManagerRegistry
     */
    private $doctrine;

    /**
     * @var FixturesExecutorInterface
     */
    private $fixturesExecutor;

    /**
     * @var FixturesFinderInterface|FixturesFinder
     */
    private $fixturesFinder;

    /**
     * @var FixturesLoaderInterface
     */
    private $fixturesLoader;

    /**
     * @var LoaderInterface
     */
    private $loader;

    /**
     * @var LoaderGeneratorInterface
     */
    private $loaderGenerator;

    /**
     * @param string                    $name             Command name
     * @param ManagerRegistry           $doctrine
     * @param LoaderInterface           $loader
     * @param FixturesLoaderInterface   $fixturesLoader
     * @param FixturesFinderInterface   $fixturesFinder
     * @param BundlesResolverInterface  $bundlesResolver
     * @param LoaderGeneratorInterface  $loaderGenerator
     * @param FixturesExecutorInterface $fixturesExecutor
     */
    public function __construct(
        $name,
        ManagerRegistry $doctrine,
        LoaderInterface $loader,
        FixturesLoaderInterface $fixturesLoader,
        FixturesFinderInterface $fixturesFinder,
        BundlesResolverInterface $bundlesResolver,
        LoaderGeneratorInterface $loaderGenerator,
        FixturesExecutorInterface $fixturesExecutor
    ) {
        $this->doctrine = $doctrine;
        $this->loader = $loader;
        $this->fixturesLoader = $fixturesLoader;
        $this->fixturesFinder = $fixturesFinder;
        $this->bundlesResolver = $bundlesResolver;
        $this->loaderGenerator = $loaderGenerator;
        $this->fixturesExecutor = $fixturesExecutor;

        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Load data fixtures to your database.')
            ->addOption(
                'bundle',
                'b',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Bundles where fixtures should be loaded.'
            )
            ->addOption(
                'manager',
                'em',
                InputOption::VALUE_REQUIRED,
                'The entity manager to use for this command.'
            )
            ->addOption(
                'append',
                null,
                InputOption::VALUE_NONE,
                'Append the data fixtures instead of deleting all data from the database first.'
            )
        ;

        if ($this->doctrine instanceof Registry) {
            $this->addOption('purge-with-truncate',
                null,
                InputOption::VALUE_NONE,
                'Purge data by using a database-level TRUNCATE statement when using Doctrine ORM.'
            );
        }
    }

    /**
     * {@inheritdoc}
     *
     * \RuntimeException Unsupported Application type
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (false !== strpos($input->getFirstArgument(), 'hautelook_alice:fixtures:load')
            || false !== strpos($input->getFirstArgument(), 'h:f:l')
        ) {
            $output->writeln('<comment>The use of "hautelook_alice:fixtures:load" command is deprecated since 1.0 and will be removed in 2.0. Use the
"hautelook_alice:doctrine:fixtures:load" instead.</comment>');
        }

        // Warn the user that the database will be purged
        // Ask him to confirm his choice
        if ($input->isInteractive() && !$input->getOption('append')) {
            if (false === $this->askConfirmation(
                    $input,
                    $output,
                    '<question>Careful, database will be purged. Do you want to continue y/N ?</question>',
                    false
                )
            ) {
                return;
            }
        }

        $manager = $this->doctrine->getManager($input->getOption('manager'));
        $environment = $input->getOption('env');
        $bundles = $input->getOption('bundle');

        /** @var Application $application */
        $application = $this->getApplication();
        if (false === $application instanceof Application) {
            throw new \RuntimeException('Expected Symfony\Bundle\FrameworkBundle\Console\Application application.');
        }

        // Get bundles
        if (true === empty($bundles)) {
            $bundles = $application->getKernel()->getBundles();
        } else {
            $bundles = $this->bundlesResolver->resolveBundles($application, $bundles);
        }

        $fixtures = $this->fixturesFinder->getFixtures($application->getKernel(), $bundles, $environment, $this->getContainer());

        $output->writeln(sprintf('  <comment>></comment> <info>%s</info>', 'fixtures found:'));
        foreach ($fixtures as $fixture) {
            $output->writeln(sprintf('      <comment>-</comment> <info>%s</info>', $fixture));
        }

        $truncate = $input->hasOption('purge-with-truncate') ? $input->getOption('purge-with-truncate') : false;

        $this->fixturesExecutor->execute(
            $manager,
            $this->loaderGenerator->generate($this->loader, $this->fixturesLoader, $bundles, $environment),
            $fixtures,
            $input->getOption('append'),
            function ($message) use ($output) {
                $output->writeln(sprintf('  <comment>></comment> <info>%s</info>', $message));
            },
            $truncate
        );
        $output->writeln(sprintf('  <comment>></comment> <info>%s</info>', 'fixtures loaded'));
    }

    /**
     * Prompt to the user a message to ask him a confirmation.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param string          $question
     * @param bool            $default
     *
     * @return bool User choice.
     */
    private function askConfirmation(InputInterface $input, OutputInterface $output, $question, $default)
    {
        if (false === class_exists('Symfony\Component\Console\Question\ConfirmationQuestion')) {
            /** @var DialogHelper $dialogHelper */
            $dialogHelper = $this->getHelperSet()->get('dialog');

            return $dialogHelper->askConfirmation($output, $question, $default);
        }

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelperSet()->get('question');
        $question = new ConfirmationQuestion($question, $default);

        return (boolean) $questionHelper->ask($input, $output, $question);
    }
}
