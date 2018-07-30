<?php

namespace PiggyCrates\Tasks;

use PiggyCrates\Main;
use PiggyCustomEnchants\CustomEnchants\CustomEnchants;
use pocketmine\block\Block;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\scheduler\Task;

/**
 * Class DropsTask
 * @package PiggyCrates\Tasks
 */
class DropsTask extends Task
{
    /** @var Main */
    private $plugin;
    /** @var Player */
    private $player;
    /** @var Block */
    private $block;
    /** @var string */
    private $type;
    /** @var bool */
    private $startingTitleComplete = false;
    /** @var array */
    private $drops;
    /** @var array */
    private $pickedDrops;
    /** @var array */
    private $items;

    /**
     * DropsTask constructor.
     * @param Main $plugin
     * @param Player $player
     * @param Block $block
     * @param string $type
     * @param array $drops
     * @param array $pickedDrops
     */
    public function __construct(Main $plugin, Player $player, Block $block, string $type, array $drops, array $pickedDrops)
    {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->block = $block;
        $this->type = $type;
        $this->drops = $drops;
        $this->pickedDrops = $pickedDrops;
    }

    /**
     * @param int $currentTick
     * @return bool
     */
    public function onRun(int $currentTick)
    {
        $player = $this->player;
        $item = $player->getInventory()->getItemInHand();
        if (!$this->startingTitleComplete) {
            $player->addTitle("§aYou have recieved:");
            $this->startingTitleComplete = true;
            return false;
        }
        $pickedDrop = reset($this->pickedDrops);
        $values = $this->drops[$pickedDrop];
        $i = Item::get($values["id"], $values["meta"], $values["amount"]);
        $i->setCustomName($values["name"]);
        if (isset($values["enchantments"])) {
            foreach ($values["enchantments"] as $enchantment => $enchantmentinfo) {
                $level = $enchantmentinfo["level"];
                /** @var CE $ce */
                $ce = $this->plugin->getServer()->getPluginManager()->getPlugin("PiggyCustomEnchants");
                if (!is_null($ce) && !is_null($enchant = CustomEnchants::getEnchantmentByName($enchantment))) {
                    $i = $ce->addEnchantment($i, $enchantment, $level);
                } else {
                    if (!is_null($enchant = Enchantment::getEnchantmentByName($enchantment))) {
                        $i->addEnchantment(new EnchantmentInstance($enchant, $level));
                    }
                }
            }
        }
        if (isset($values["lore"])) {
            $i->setLore([$values["lore"]]);
        }
        if (isset($values["command"])) {
            $cmd = $values["command"];
            $cmd = str_replace(["%PLAYER%"], [$player->getName()], $cmd);
            $this->plugin->getServer()->dispatchCommand(new ConsoleCommandSender(), $cmd);
        }
        $playerName = $player->getName();
        $this->items[] = $i;
        $player->addTitle("", "");
        $player->addTitle("", $values["amount"] . " " . $values["name"]);
        $this->plugin->getServer()->broadcastMessage("§a$playerName §bhas opened a §3$type §bCrate §band has received: §d$this->items[]");
        $particles = "pocketmine\\level\\particle\\" . ucfirst($this->plugin->getCrateDropParticle($this->type)) . "Particle";
        if (class_exists($particles)) {
            $this->block->getLevel()->addParticle(new $particles($this->block->add(0, 2)));
        }
        array_shift($this->pickedDrops);
        if (count($this->pickedDrops) <= 0) {
            $player->getInventory()->removeItem($item->setCount(1));
            $player->getInventory()->addItem(...$this->items);
            $this->plugin->getScheduler()->cancelTask($this->getHandler()->getTaskId());
        }
        return true;
    }
}
