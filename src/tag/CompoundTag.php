<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\nbt\tag;

use pocketmine\nbt\NBT;
use pocketmine\nbt\NBTStream;
use pocketmine\nbt\ReaderTracker;
use function assert;
use function count;
use function current;
use function get_class;
use function gettype;
use function is_a;
use function is_int;
use function is_object;
use function key;
use function next;
use function reset;
use function str_repeat;

/**
 * @phpstan-implements \ArrayAccess<string, mixed>
 * @phpstan-implements \Iterator<string, NamedTag>
 */
class CompoundTag extends NamedTag implements \ArrayAccess, \Iterator, \Countable{
	use NoDynamicFieldsTrait;

	/** @var NamedTag[] */
	private $value = [];

	/**
	 * @param NamedTag[] $value
	 */
	public function __construct(string $name = "", array $value = []){
		parent::__construct($name);

		foreach($value as $tag){
			$this->setTag($tag);
		}
	}

	public function count() : int{
		return count($this->value);
	}

	/**
	 * @return int
	 */
	public function getCount(){
		return count($this->value);
	}

	/**
	 * @return NamedTag[]
	 */
	public function getValue(){
		return $this->value;
	}

	/*
	 * Here follows many functions of misery for the sake of type safety. We really needs generics in PHP :(
	 */

	/**
	 * Returns the tag with the specified name, or null if it does not exist.
	 *
	 * @phpstan-template T of NamedTag
	 *
	 * @param string $expectedClass Class that extends NamedTag
	 * @phpstan-param class-string<T> $expectedClass
	 *
	 * @phpstan-return T|null
	 * @throws \RuntimeException if the tag exists and is not of the expected type (if specified)
	 */
	public function getTag(string $name, string $expectedClass = NamedTag::class) : ?NamedTag{
		assert(is_a($expectedClass, NamedTag::class, true));
		$tag = $this->value[$name] ?? null;
		if($tag !== null and !($tag instanceof $expectedClass)){
			throw new \RuntimeException("Expected a tag of type $expectedClass, got " . get_class($tag));
		}

		return $tag;
	}

	/**
	 * Returns the ListTag with the specified name, or null if it does not exist. Triggers an exception if a tag exists
	 * with that name and the tag is not a ListTag.
	 */
	public function getListTag(string $name) : ?ListTag{
		return $this->getTag($name, ListTag::class);
	}

	/**
	 * Returns the CompoundTag with the specified name, or null if it does not exist. Triggers an exception if a tag
	 * exists with that name and the tag is not a CompoundTag.
	 */
	public function getCompoundTag(string $name) : ?CompoundTag{
		return $this->getTag($name, CompoundTag::class);
	}

	/**
	 * Sets the specified NamedTag as a child tag of the CompoundTag at the offset specified by the tag's name. If a tag
	 * already exists at the offset and the types do not match, an exception will be thrown unless $force is true.
	 */
	public function setTag(NamedTag $tag, bool $force = false) : void{
		if(!$force){
			$existing = $this->value[$tag->__name] ?? null;
			if($existing !== null and !($tag instanceof $existing)){
				throw new \RuntimeException("Cannot set tag at \"$tag->__name\": tried to overwrite " . get_class($existing) . " with " . get_class($tag));
			}
		}
		$this->value[$tag->__name] = $tag;
	}

	/**
	 * Removes the child tags with the specified names from the CompoundTag. This function accepts a variadic list of
	 * strings.
	 *
	 * @param string[] ...$names
	 */
	public function removeTag(string ...$names) : void{
		foreach($names as $name){
			unset($this->value[$name]);
		}
	}

	/**
	 * Returns whether the CompoundTag contains a child tag with the specified name.
	 *
	 * @phpstan-param class-string<NamedTag> $expectedClass
	 */
	public function hasTag(string $name, string $expectedClass = NamedTag::class) : bool{
		assert(is_a($expectedClass, NamedTag::class, true));
		return ($this->value[$name] ?? null) instanceof $expectedClass;
	}

	/**
	 * Returns the value of the child tag with the specified name, or $default if the tag doesn't exist. If the child
	 * tag is not of type $expectedType, an exception will be thrown, unless a default is given and $badTagDefault is
	 * true.
	 *
	 * @param mixed  $default
	 * @param bool   $badTagDefault Return the specified default if the tag is not of the expected type.
	 * @phpstan-param class-string<NamedTag> $expectedClass
	 *
	 * @return mixed
	 */
	public function getTagValue(string $name, string $expectedClass, $default = null, bool $badTagDefault = false){
		$tag = $this->getTag($name, $badTagDefault ? NamedTag::class : $expectedClass);
		if($tag instanceof $expectedClass){
			return $tag->getValue();
		}

		if($default === null){
			throw new \RuntimeException("Tag with name \"$name\" " . ($tag !== null ? "not of expected type" : "not found") . " and no valid default value given");
		}

		return $default;
	}

	/*
	 * The following methods are wrappers around getTagValue() with type safety.
	 */

	public function getByte(string $name, ?int $default = null, bool $badTagDefault = false) : int{
		return $this->getTagValue($name, ByteTag::class, $default, $badTagDefault);
	}

	public function getShort(string $name, ?int $default = null, bool $badTagDefault = false) : int{
		return $this->getTagValue($name, ShortTag::class, $default, $badTagDefault);
	}

	public function getInt(string $name, ?int $default = null, bool $badTagDefault = false) : int{
		return $this->getTagValue($name, IntTag::class, $default, $badTagDefault);
	}

	public function getLong(string $name, ?int $default = null, bool $badTagDefault = false) : int{
		return $this->getTagValue($name, LongTag::class, $default, $badTagDefault);
	}

	public function getFloat(string $name, ?float $default = null, bool $badTagDefault = false) : float{
		return $this->getTagValue($name, FloatTag::class, $default, $badTagDefault);
	}

	public function getDouble(string $name, ?float $default = null, bool $badTagDefault = false) : float{
		return $this->getTagValue($name, DoubleTag::class, $default, $badTagDefault);
	}

	public function getByteArray(string $name, ?string $default = null, bool $badTagDefault = false) : string{
		return $this->getTagValue($name, ByteArrayTag::class, $default, $badTagDefault);
	}

	public function getString(string $name, ?string $default = null, bool $badTagDefault = false) : string{
		return $this->getTagValue($name, StringTag::class, $default, $badTagDefault);
	}

	/**
	 * @param int[]|null $default
	 *
	 * @return int[]
	 */
	public function getIntArray(string $name, ?array $default = null, bool $badTagDefault = false) : array{
		return $this->getTagValue($name, IntArrayTag::class, $default, $badTagDefault);
	}

	/*
	 * The following methods are wrappers around setTag() which create appropriate tag objects on the fly.
	 */

	public function setByte(string $name, int $value, bool $force = false) : void{
		$this->setTag(new ByteTag($name, $value), $force);
	}

	public function setShort(string $name, int $value, bool $force = false) : void{
		$this->setTag(new ShortTag($name, $value), $force);
	}

	public function setInt(string $name, int $value, bool $force = false) : void{
		$this->setTag(new IntTag($name, $value), $force);
	}

	public function setLong(string $name, int $value, bool $force = false) : void{
		$this->setTag(new LongTag($name, $value), $force);
	}

	public function setFloat(string $name, float $value, bool $force = false) : void{
		$this->setTag(new FloatTag($name, $value), $force);
	}

	public function setDouble(string $name, float $value, bool $force = false) : void{
		$this->setTag(new DoubleTag($name, $value), $force);
	}

	public function setByteArray(string $name, string $value, bool $force = false) : void{
		$this->setTag(new ByteArrayTag($name, $value), $force);
	}

	public function setString(string $name, string $value, bool $force = false) : void{
		$this->setTag(new StringTag($name, $value), $force);
	}

	/**
	 * @param int[]  $value
	 */
	public function setIntArray(string $name, array $value, bool $force = false) : void{
		$this->setTag(new IntArrayTag($name, $value), $force);
	}

	/**
	 * @param string $offset
	 *
	 * @return bool
	 */
	public function offsetExists($offset): bool{
		return isset($this->value[$offset]);
	}

	/**
	 * @param string $offset
	 *
	 * @return mixed|null|\ArrayAccess
	 */
	public function offsetGet($offset): mixed{
		if(isset($this->value[$offset])){
			if($this->value[$offset] instanceof \ArrayAccess){
				return $this->value[$offset];
			}else{
				return $this->value[$offset]->getValue();
			}
		}

		assert(false, "Offset $offset not found");

		return null;
	}

	/**
	 * @param string|null $offset
	 * @param NamedTag    $value
	 *
	 * @throws \InvalidArgumentException if offset is null
	 * @throws \TypeError if $value is not a NamedTag object
	 */
	public function offsetSet($offset, $value):  void{
		if($offset === null){
			throw new \InvalidArgumentException("Array access push syntax is not supported");
		}
		if($value instanceof NamedTag){
			if($offset !== $value->getName()){
				throw new \UnexpectedValueException("Given tag has a name which does not match the offset given (offset: \"$offset\", tag name: \"" . $value->getName() . "\")");
			}
			$this->value[$offset] = $value;
		}else{
			throw new \TypeError("Value set by ArrayAccess must be an instance of " . NamedTag::class . ", got " . (is_object($value) ? " instance of " . get_class($value) : gettype($value)));
		}
	}

	public function offsetUnset($offset): void{
		unset($this->value[$offset]);
	}

	public function getType() : int{
		return NBT::TAG_Compound;
	}

	public function read(NBTStream $nbt, ReaderTracker $tracker) : void{
		$this->value = [];
		$tracker->protectDepth(function() use($nbt, $tracker) : void{
			do{
				$tag = $nbt->readTag($tracker);
				if($tag !== null){
					if(isset($this->value[$tag->__name])){
						//this is technically a corruption case, but it's very common on older PM worlds (pretty much every
						//furnace in PM worlds prior to 2017 is affected), and since we can't extricate this borked data
						//from the rest in Anvil/McRegion worlds, we can't barf on this - it would result in complete loss
						//of the chunk.
						//TODO: add a flag to enable throwing on this (strict mode)
						continue;
					}
					$this->value[$tag->__name] = $tag;
				}
			}while($tag !== null);
		});
	}

	public function write(NBTStream $nbt) : void{
		foreach($this->value as $tag){
			$nbt->writeTag($tag);
		}
		$nbt->writeEnd();
	}

	public function toString(int $indentation = 0) : string{
		$str = str_repeat("  ", $indentation) . get_class($this) . ": " . ($this->__name !== "" ? "name='$this->__name', " : "") . "value={\n";
		foreach($this->value as $tag){
			$str .= $tag->toString($indentation + 1) . "\n";
		}
		return $str . str_repeat("  ", $indentation) . "}";
	}

	public function __clone(){
		foreach($this->value as $key => $tag){
			$this->value[$key] = $tag->safeClone();
		}
	}

	public function next() : void{
		next($this->value);
	}

	public function valid() : bool{
		return key($this->value) !== null;
	}

	public function key() : string{
		$k = key($this->value);
		if($k === null){
			throw new \OutOfBoundsException("Iterator already reached the end");
		}
		if(is_int($k)){
			/* PHP arrays are idiotic and cast keys like "1" to int(1)
			 * TODO: perhaps we should consider using a \Ds\Map for this?
			 */
			$k = (string) $k;
		}

		return $k;
	}

	public function current() : NamedTag{
		$current = current($this->value);
		if($current === false){
			throw new \OutOfBoundsException("Iterator already reached the end");
		}
		return $current;
	}

	public function rewind() : void{
		reset($this->value);
	}

	protected function equalsValue(NamedTag $that) : bool{
		if(!($that instanceof $this) or $this->count() !== $that->count()){
			return false;
		}

		foreach($this as $k => $v){
			$other = $that->getTag($k);
			if($other === null or !$v->equals($other)){
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns a copy of this CompoundTag with values from the given CompoundTag merged into it. Tags that exist both in
	 * this tag and the other will be overwritten by the tag in the other.
	 *
	 * This deep-clones all tags.
	 */
	public function merge(CompoundTag $other) : CompoundTag{
		$new = clone $this;

		foreach($other as $namedTag){
			$new->setTag(clone $namedTag);
		}

		return $new;
	}
}
