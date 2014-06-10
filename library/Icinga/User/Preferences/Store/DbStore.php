<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\User\Preferences\Store;

use Exception;
use Zend_Db_Select;
use Icinga\Exception\NotReadableError;
use Icinga\Exception\NotWritableError;
use Icinga\User\Preferences;
use Icinga\User\Preferences\PreferencesStore;

/**
 * Load and save user preferences by using a database
 */
class DbStore extends PreferencesStore
{
    /**
     * Column name for username
     */
    const COLUMN_USERNAME = 'username';

    /**
     * Column name for preference
     */
    const COLUMN_PREFERENCE = 'key';

    /**
     * Column name for value
     */
    const COLUMN_VALUE = 'value';

    /**
     * Table name
     *
     * @var string
     */
    protected $table = 'preference';

    /**
     * Stored preferences
     *
     * @var array
     */
    protected $preferences = array();

    /**
     * Set the table to use
     *
     * @param   string  $table  The table name
     */
    public function setTable($table)
    {
        $this->table = $table;
    }

    /**
     * Initialize the store
     */
    protected function init()
    {

    }

    /**
     * Load preferences from the database
     *
     * @return  array
     *
     * @throws  NotReadableError    In case the database operation failed
     */
    public function load()
    {
        try {
            $select = new Zend_Db_Select($this->getStoreConfig()->connection->getConnection());
            $result = $select->from($this->table, array(self::COLUMN_PREFERENCE, self::COLUMN_VALUE))
                ->where(self::COLUMN_USERNAME . ' = ?', $this->getUser()->getUsername())
                ->query()->fetchAll();
        } catch (Exception $e) {
            throw new NotReadableError(
                'Cannot fetch preferences for user ' . $this->getUser()->getUsername() . ' from database', 0, $e
            );
        }

        if ($result !== false) {
            $values = array();
            foreach ($result as $row) {
                $values[$row->{self::COLUMN_PREFERENCE}] = $row->{self::COLUMN_VALUE};
            }
            $this->preferences = $values;
        }

        return $this->preferences;
    }

    /**
     * Save the given preferences in the database
     *
     * @param   Preferences     $preferences    The preferences to save
     */
    public function save(Preferences $preferences)
    {
        $preferences = $preferences->toArray();

        $toBeInserted = array_diff_key($preferences, $this->preferences);
        if (!empty($toBeInserted)) {
            $this->insert($toBeInserted);
        }

        $current = $this->preferences;
        $toBeUpdated = array();
        foreach (array_filter(
            array_keys(array_intersect_key($preferences, $this->preferences)),
            function ($k) use ($current, $preferences) { return $current[$k] == $preferences[$k] ? false : true; }
        ) as $key) {
            $toBeUpdated[$key] = $preferences[$key];
        }
        if (!empty($toBeUpdated)) {
            $this->update($toBeUpdated);
        }

        $toBeDeleted = array_keys(array_diff_key($this->preferences, $preferences));
        if (!empty($toBeDeleted)) {
            $this->delete($toBeDeleted);
        }
    }

    /**
     * Insert the given preferences into the database
     *
     * @param   array   $preferences    The preferences to insert
     *
     * @throws  NotWritableError        In case the database operation failed
     */
    protected function insert(array $preferences)
    {
        $db = $this->getStoreConfig()->connection->getConnection();

        try {
            foreach ($preferences as $key => $value) {
                $db->insert(
                    $this->table,
                    array(
                        self::COLUMN_USERNAME => $this->getUser()->getUsername(),
                        $db->quoteIdentifier(self::COLUMN_PREFERENCE) => $key,
                        self::COLUMN_VALUE => $value
                    )
                );
            }
        } catch (Exception $e) {
            throw new NotWritableError(
                'Cannot insert preferences for user ' . $this->getUser()->getUsername() . ' into database', 0, $e
            );
        }
    }

    /**
     * Update the given preferences in the database
     *
     * @param   array   $preferences    The preferences to update
     *
     * @throws  NotWritableError        In case the database operation failed
     */
    protected function update(array $preferences)
    {
        $db = $this->getStoreConfig()->connection->getConnection();

        try {
            foreach ($preferences as $key => $value) {
                $db->update(
                    $this->table,
                    array(self::COLUMN_VALUE => $value),
                    array(
                        self::COLUMN_USERNAME . '=?' => $this->getUser()->getUsername(),
                        $db->quoteIdentifier(self::COLUMN_PREFERENCE) . '=?' => $key
                    )
                );
            }
        } catch (Exception $e) {
            throw new NotWritableError(
                'Cannot update preferences for user ' . $this->getUser()->getUsername() . ' in database', 0, $e
            );
        }
    }

    /**
     * Delete the given preference names from the database
     *
     * @param   array   $preferenceKeys     The preference names to delete
     *
     * @throws  NotWritableError            In case the database operation failed
     */
    protected function delete(array $preferenceKeys)
    {
        $db = $this->getStoreConfig()->connection->getConnection();

        try {
            $db->delete(
                $this->table,
                array(
                    self::COLUMN_USERNAME . '=?' => $this->getUser()->getUsername(),
                    $db->quoteIdentifier(self::COLUMN_PREFERENCE) . ' IN (?)' => $preferenceKeys
                )
            );
        } catch (Exception $e) {
            throw new NotWritableError(
                'Cannot delete preferences for user ' . $this->getUser()->getUsername() . ' from database', 0, $e
            );
        }
    }
}
