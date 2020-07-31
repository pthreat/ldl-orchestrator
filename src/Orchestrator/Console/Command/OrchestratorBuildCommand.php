<?php declare(strict_types=1);

namespace LDL\Orchestrator\Console\Command;

use LDL\DependencyInjection\Container\Config\ContainerConfigFactory;
use LDL\DependencyInjection\Container\Writer\ContainerFileWriter;
use LDL\Env\Config\EnvConfigFactory;
use LDL\Env\Writer\EnvFileWriter;
use LDL\Orchestrator\Builder\Builder;
use LDL\Orchestrator\Builder\Config\Config\BuilderConfig;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OrchestratorBuildCommand extends AbstractOrchestratorCommand
{
    public const COMMAND_NAME = 'build';

    public function configure() : void
    {
        parent::configure();

        $this->setName(self::COMMAND_NAME)
            ->setDescription('Builds container dependencies');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            parent::execute($input, $output);

            $this->build($input, $output);

            return parent::EXIT_SUCCESS;

        }catch(\Exception $e){
            $output->writeln("<error>{$e->getMessage()}</error>");
            return parent::EXIT_ERROR;
        }
    }

    private function build(
        InputInterface $input,
        OutputInterface $output
    ) : void
    {
        $start = hrtime(true);

        try {

            $title = sprintf(
                '[ Building container in "%s" mode ]',
                $this->orchestrator->isDevMode() ? 'DEV' : 'PROD'
            );

            $output->writeln("\n<info>$title</info>\n");

            $progressBar = new ProgressBar($output);
            $progressBar->start();

            $envWriter = new EnvFileWriter();
            $containerWriter = new ContainerFileWriter();

            $envConfig = EnvConfigFactory::factory(
                $this->orchestrator->getEnvBuilder()->getFinder(),
                $this->orchestrator->getEnvBuilder()->getCompiler(),
                $envWriter
            );

            $containerConfig = ContainerConfigFactory::factory(
                $this->orchestrator->getLDLContainerBuilder()->getServiceFinder(),
                $this->orchestrator->getLDLContainerBuilder()->getServiceCompiler(),
                $this->orchestrator->getLDLContainerBuilder()->getServiceReader(),
                $this->orchestrator->getLDLContainerBuilder()->getCompilerPassFinder(),
                $this->orchestrator->getLDLContainerBuilder()->getCompilerPassReader(),
                $containerWriter
            );

            $orchestratorConfig = $this->orchestrator->getConfig();

            $orchestratorJson = $this->orchestrator->compileJSON(
                $orchestratorConfig,
                $envConfig,
                $containerConfig
            );

            $orchestratorLock = $this->orchestrator->compileLock($orchestratorJson);

            $jsonConfig = json_encode($orchestratorJson->toArray(), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
            $lockConfig = json_encode($orchestratorLock->toArray(), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);

            if(false === $this->orchestrator->isDevMode()){
                $this->orchestrator->getBuilderConfigWriter()->write($jsonConfig);
                $this->orchestrator->getBuilderConfigWriter()->write($lockConfig, false);
            }

            $output->writeln("");

        }catch(\Exception $e){

            $output->writeln("\n<error>Build failed!</error>\n");
            $output->writeln("\n<error>{$e->getMessage()}</error>\n");

            return;
        }

        $progressBar->finish();

        $end = hrtime(true);
        $total = round((($end - $start) / 1e+6) / 1000,2);

        $output->writeln("\n<info>Took: $total seconds</info>");
    }

    public function getOrchestrator() : ?Builder
    {
        return $this->orchestrator;
    }

}