<?php

namespace dwalker109\Battler;

use dwalker109\Battle\Battle;
use dwalker109\Skills\SkillContract;
use dwalker109\Tools;

abstract class Battler
{
    // Handle to the current battle
    public $battle;

    // Battler detail and baseline/turn attributes
    private $name;
    private $attributes;
    private $turn_attributes;

    // Special skills loaded for this instance
    protected $pre_turn_skills = [];
    protected $post_turn_skills = [];

    // Special skills to load (override in implementing class)
    protected $skills = [];

    // Attribute definitions (override in implementing class)
    protected $definitions = [
        'health' => ['min' => 0, 'max' => 100],
        'strength' => ['min' => 0, 'max' => 100],
        'defence' => ['min' => 0, 'max' => 100],
        'speed' => ['min' => 0, 'max' => 100],
        'luck' => ['min' => 0.00, 'max' => 1.00],
    ];

    /**
     * Initialise a new combatant and generate attributes.
     *
     * @param string $name
     * @param Battle $battle
     */
    public function __construct($name, Battle $battle)
    {
        $this->name = $name;
        $this->battle = $battle;

        // Build attributes from definitions
        foreach ($this->definitions as $attribute => $constraints) {
            $this->attributes[$attribute] = Tools::randomNumber(
                $constraints['min'], $constraints['max']
            );
        }

        // Add additional attributes
        $this->attributes += [
            // Display
            'name' => ucfirst($this->name),
            'type' => (new \ReflectionClass($this))->getShortName(),

            // Status and effects
            'was_hit' => false,
            'stunned' => false,
            'evaded' => false,
        ];

        // Register special skills
        foreach ($this->skills as $skill) {
            $skill = new $skill();

            switch ($skill->type()) {
                case SkillContract::PRE:
                    $this->pre_turn_skills[] = $skill;
                    break;

                case SkillContract::POST:
                    $this->post_turn_skills[] = $skill;
                    break;
            }
        }

        // Prepare for next turn.
        $this->initNextTurn();
    }

    /**
     * Prepate for next turn.
     */
    public function initNextTurn()
    {
        $this->turn_attributes = $this->attributes;
    }

    /**
     * Set/return attribute properties for this turn.
     *
     * @param array $values
     *
     * @return srdClass
     */
    public function attr($values = null)
    {
        // Set any turn attributes passed, ignoring any which don't exist
        if (is_array($values)) {
            $filtered = array_intersect_key($values, $this->turn_attributes);
            $this->turn_attributes = array_merge($this->turn_attributes, $filtered);
        }

        return (object) $this->turn_attributes;
    }

    /**
     * Attack the opposing Battler.
     *
     * @param Battler $opponent
     */
    public function attack(Battler $opponent)
    {
        // Miss this attack if attacker is stunned
        if ($this->attr()->stunned) {
            $this->battle->pushMessage(
                "{$this->attr()->name} is stunned and cannot attack"
            );

            return;
        }

        // Use opponent's luck to calculate if the attack should miss
        if (!$opponent->evade($this)) {
            $this->battle->pushMessage(
                "{$this->attr()->name} attacked with {$this->attr()->strength} strength"
            );

            $opponent->attr(['was_hit' => true]);

            $opponent->defend($this);
        }
    }

    /**
     * Try to evade an incoming attack.
     *
     * @param Battler $opponent
     *
     * @return bool
     */
    public function evade(Battler $opponent)
    {
        $evaded = Tools::randomNumber(0.00, 1.00) <= $this->attr()->luck;

        if ($evaded) {
            $this->battle->pushMessage(
                "{$opponent->attr()->name} missed their attack as " .
                "{$this->attr()->name} was lucky and managed to evade"
            );

            $this->attr(['evaded' => true]);
        }

        return $evaded;
    }

    /**
     * Defend against an attack from the opposing Battler.
     *
     * @param Battler $opponent
     */
    public function defend(Battler $opponent)
    {
        // Calculate and apply damage (prevent negative damage)
        $damage = max(0, $opponent->attr()->strength - $this->attr()->defence);
        $this->takeDamage($damage, $opponent);
    }

    /**
     * Recieve damage and ensure attributes are updated properly.
     *
     * @param int     $damage
     * @param Battler $opponent
     */
    public function takeDamage(int $damage, Battler $opponent)
    {
        // Set health instantly in both persistent and turn attribs, prevent negative health
        $this->attributes['health'] = $this->turn_attributes['health'] = max(
            0, $this->attr()->health - $damage
        );

        $this->battle->pushMessage(
            "{$this->attr()->name} received {$damage} damage and has {$this->attr()->health} health remaining"
        );

        // End the battle if damage is fatal
        if ($this->attr()->health <= 0) {
            $this->battle->is_active = false;
            $this->battle->pushMessage("{$opponent->attr()->name} is the winner!");
        }
    }

    /**
     * Run any registered pre turn special skills.
     */
    public function preTurnSkills()
    {
        if (!$this->battle->is_active) {
            return;
        }

        foreach ($this->pre_turn_skills as $skill) {
            $skill->activate($this);
        }
    }

    /**
     * Run any registered post turn special skills.
     */
    public function postTurnSkills()
    {
        if (!$this->battle->is_active) {
            return;
        }

        foreach ($this->post_turn_skills as $skill) {
            $skill->activate($this);
        }
    }
}
