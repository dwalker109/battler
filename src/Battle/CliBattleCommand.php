<?php

namespace dwalker109\Battle;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class CliBattleCommand extends Command
{
    /**
     * Set up this Symfony command.
     */
    protected function configure()
    {
        $this->setName('battle:run');
        $this->setDescription('Runs a CLI based battle.');
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Each combatant's name will be requested and stored here
        $names = [
            'first' => null,
            'second' => null,
        ];

        // Get each name from CLI and validate the length
        foreach ($names as $label => $name) {
            $helper = $this->getHelper('question');
            $question = new Question("Please enter the name of the {$label} combatant: ");
            $question->setValidator(function ($answer) {
                if (strlen($answer) > 32) {
                    throw new \RuntimeException(
                        'The combatant\'s name must be 32 characters or less.'
                    );
                }

                if (strlen($answer) < 1) {
                    throw new \RuntimeException(
                        'You did not enter a name for the combatant.'
                    );
                }

                return $answer;
            });

            $names[$label] = $helper->ask($input, $output, $question);
        }

        // Create the battle, generating a battler for each name
        $battle = new Battle($names['first'], $names['second']);

        // Output a table detailing each combatant
        $table = new Table($output);
        $table->setHeaders(
            ['Order', 'Name', 'Type', 'Health', 'Strength', 'Defence', 'Speed', 'Luck']
        );

        foreach (['player_current', 'player_opponent'] as $index => $player) {
            $table->addRow(
                [
                    $index === 0 ? 'First' : 'Second',
                    $battle->{$player}->attr()->name,
                    $battle->{$player}->attr()->type,
                    $battle->{$player}->attr()->health,
                    $battle->{$player}->attr()->strength,
                    $battle->{$player}->attr()->defence,
                    $battle->{$player}->attr()->speed,
                    $battle->{$player}->attr()->luck,
                ]
            );
        }

        $table->render();

        // Run the game loop
        while ($battle->is_active) {
            $battle->calculateTurn();
            $this->render($battle, $output);
        }
    }

    /**
     * Output the current 'frame' of the Battle to the CLI.
     *
     * @param Battle          $battle
     * @param OutputInterface $output
     */
    private function render(Battle $battle, OutputInterface $output)
    {
        // Get messages
        $output->writeln(implode(', ', $battle->popMessages()));
    }
}
