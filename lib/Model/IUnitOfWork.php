<?php

interface IUnitOfWork {

    /**
     * Persists a transaction
     *
     * @return bool
     */
    public function commit();

    /**
     * Rollback a transaction
     *
     * @return void
     */
    public function rollback();

    /**
     * Free the memory
     *
     * @return void
     */
    public function clearAll();
}
