<?php

namespace Mix\Micro\Service;

/**
 * Interface NodeInterface
 * @package Mix\Micro\Service
 */
interface NodeInterface extends \JsonSerializable
{

    /**
     * Get id
     * @return string
     */
    public function getID();

    /**
     * Get name
     * @return string
     */
    public function getName();

    /**
     * Get address
     * @return string
     */
    public function getAddress();

    /**
     * Get services
     * @return string[]
     */
    public function getServices();

    /**
     * Append service
     * @param string $id
     * @param string $name
     */
    public function withAddedService(string $id, string $name);

}
