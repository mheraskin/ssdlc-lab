<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\TotpService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Print the current TOTP code for a user. DEV-ONLY — refuses to run in prod.
 *
 * Lets you exercise the MFA flow locally without installing an authenticator app:
 *   $ php bin/console app:totp client@example.com
 *   Current TOTP code: 482107 (valid for ~17s)
 *
 * In automated tests, the same code is computed inline via OTPHP — no command needed.
 */
#[AsCommand(name: 'app:totp', description: 'Print the current TOTP code for a user (DEV ONLY)')]
class TotpCurrentCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly TotpService $totp,
        #[Autowire('%kernel.environment%')]
        private readonly string $appEnv,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'The user email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('prod' === $this->appEnv) {
            $io->error('app:totp is disabled in the production environment.');

            return Command::FAILURE;
        }

        $email = (string) $input->getArgument('email');
        $user = $this->users->findOneByEmail($email);
        if (null === $user) {
            $io->error(sprintf('User "%s" not found.', $email));

            return Command::FAILURE;
        }

        if (null === $user->getTotpSecret()) {
            $io->warning(sprintf('User "%s" has no TOTP secret — start enrollment via POST /api/totp/setup first.', $email));

            return Command::FAILURE;
        }

        $totp = $this->totp->totpFor($user);
        $now = (int) (microtime(true));
        $period = $totp->getPeriod();
        $remaining = $period - ($now % $period);

        $io->writeln(sprintf(
            'Current TOTP code: <info>%s</info>  (valid for ~%ds; %s)',
            $totp->now(),
            $remaining,
            $user->isTotpEnabled() ? 'enrollment confirmed' : 'enrollment NOT YET confirmed — use this code on /api/totp/enable',
        ));

        return Command::SUCCESS;
    }
}
