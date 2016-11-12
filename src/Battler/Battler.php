<?php

namespace dwalker109\Battler;

use dwalker109\Battle\Battle;

abstract class Battler
{
    // Handle to the current battle
    private $battle;
    
    // Basic battler properties
    private $name;
    private $health;
    private $strength;
    private $defence;
    private $speed;
    private $luck;
    
    // Action message for this turn
    private $messages = [];

    // Value constraints used during construction
    protected $property_constraints = [
        "health" => [
            "min" => 0,
            "max" => 100,
        ],
        "strength" => [
            "min" => 0,
            "max" => 100,
        ],
        "defence" => [
            "min" => 0,
            "max" => 100,
        ],
        "speed" => [
            "min" => 0,
            "max" => 100,
        ],
        "luck" => [
            "min" => 0.00,
            "max" => 1.00,
        ],
    ];
    
    /**
     * Initialise a new combatant and randomly generate properties.
     *
     * @param string $name
     * @param Battle $battle
     *
     * @return void
     */
    public function __construct($name, Battle $battle)
    {
        $this->name = $name;
        $this->battle = $battle;
        
        foreach ($this->property_constraints as $property => $constraints) {
            $this->{$property} = property_exists($this, $property)
                ? $this->random($constraints['min'], $constraints['max'])
                : 0;
        }
    }

    /**
     * Return readable property values, with (TODO!) this turn's modifiers applied.
     *
     * @return srdClass
     */
    public function read()
    {
        return (object) [
            'name' => ucfirst($this->name),
            'type' => (new \ReflectionClass($this))->getShortName(),
            'health' => $this->health,
            'strength' => $this->strength,
            'defence' => $this->defence,
            'speed' => $this->speed,
            'luck' => $this->luck,
        ];
    }
    
    /**
     * Return a random integer or float within the passed constraints.
     *
     * @param int|float $min
     * @param int|float $max
     *
     * @return int|float
     */
    private function random($min, $max)
    {
        if (is_integer($min) && is_integer($max)) {
            return mt_rand($min, $max);
        }
        
        if (is_float($min) && is_float($max)) {
            return ($min + lcg_value() * (abs($max - $min)));
        }
        
        // Something unusual was passed - return integer 0
        return 0;
    }
    
    /**
     * Attack the opposing Battler.
     *
     * @param Battler $opponent
     *
     * @return void
     */
    public function attack(Battler $opponent)
    {
        // Use opponent's luck to calculate if the attack should miss
        $attack_missed = $this->random(0.00, 1.00) < $opponent->read()->luck;

        switch ($attack_missed) {
            case true:
                $this->battle->pushMessage(
                    "{$this->read()->name} was unlucky and missed their attack"
                );
                
                break;
            
            case false;
            default:
                $this->battle->pushMessage(
                    "{$this->read()->name} attacked with {$this->read()->strength} strength"
                );
                
                $opponent->receiveAttack($this);
                
                break;
        }
    }
    
    /**
     * Receive an attack from the opposing Battler.
     *
     * @param Battler $opponent
     *
     * @return void
     */
    public function receiveAttack(Battler $opponent)
    {
        $damage = $opponent->read()->strength - $this->read()->defence;
        $this->health -= $damage;
        
        $this->battle->pushMessage("{$this->read()->name} received {$damage} damage");
        
        if (!$this->isAlive()) {
            $this->battle->is_active = false;
            $this->battle->pushMessage("{$opponent->read()->name} is the winner!");
        }
    }
    
    /**
     * Check for death.
     *
     * @return void
     */
    public function isAlive()
    {
        if ($this->health <= 0) {
            return false;
        }
        
        return true;
    }
}
