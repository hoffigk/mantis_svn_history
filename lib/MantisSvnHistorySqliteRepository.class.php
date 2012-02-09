<?php

require_once 'MantisSvnHistoryBaseRepository.class.php';

class MantisSvnHistorySqliteRepository extends MantisSvnHistoryBaseRepository
{

    protected $database_file;

    protected $db;

    public function __construct($repository_url, $username = null, $password = null)
    {
        
        $this->svn_repository = $repository_url;
        $this->svn_username = $username;
        $this->svn_password = $password;

        $this->database_file = $this->buildDatabaseFilenameByRepositoryUrl($repository_url);

        $this->createDatabaseIfNotExists();

        $this->db = $this->getDatabaseConnection();

        $this->updateSvnLog();
    }

    protected function getDatabaseConnection()
    {
        if (!$db)
        {
            $db = new SQLiteDatabase($this->database_file);
        }

        return $db;
    }

    protected function getLatestSvnRevision()
    {
        $result = $this->db->query('SELECT revision FROM revision LIMIT 1');

        if ($result->numRows() > 0)
        {
            return $result->fetchSingle();
        }

        return 7777;
    }

    protected function updateLatestSvnRevsion($number)
    {
        $this->db->queryExec('UPDATE revision SET revision=' . $number . ' WHERE rowid = 1');
    }

    protected function updateSvnLog()
    {
        $latest_revision = $this->getLatestSvnRevision();

        $log_entries_as_string = $this->retrieveSvnLogEntriesAsString($latest_revision, false);

        if (empty($log_entries_as_string))
        {
            return;
        }

        $new_latest_revision = $this->getSvnRevisionFromString($log_entries_as_string, 'revision');

        if ($new_latest_revision != $latest_revision)
        {
            $this->updateLatestSvnRevsion($new_latest_revision);

            $xml = simplexml_load_string($log_entries_as_string);

            foreach ($xml->logentry as $logentry)
            {

                $mantis_ticket_numbers = $this->extractMantisTicketsFromString((string)$logentry->msg);

                if (count($mantis_ticket_numbers) === 0)
                {
                    continue;
                }

                $current_revision = (string)$logentry[revision];

                foreach ($mantis_ticket_numbers as $mantis_ticket_number)
                {
                    $sql = sprintf('INSERT INTO mantis_tickets_revisions VALUES(%1$s, %2$s)', $mantis_ticket_number, $current_revision);
                    $this->db->queryExec($sql);
                }

                $paths = array();
                $count = 0;
                foreach ($logentry->paths->path as $path)
                {
                    $paths[] = array(
                            'path' => (string)$path,
                            'action' => (string)$path['action']
                    );

                    if ($count++ == 100)
                        break;
                }

                $sql = 'INSERT INTO logentries (
                            "revision",
                            "author",
                            "date",
                            "msg",
                            "paths"
                        ) VALUES (
                            ' . (int)$logentry[revision] . ',
                            \'' . sqlite_escape_string($logentry->author) . '\',
                            \'' . sqlite_escape_string(gmdate('D, d M Y H:i:s', strtotime($logentry->date) + date('Z'))) . '\',
                            \'' . sqlite_escape_string((string)$logentry->msg) . '\',
                            \'' . sqlite_escape_string(json_encode($paths)) . '\'
                        );';

                $this->db->queryExec($sql, $error_msg);
            }
        }
    }

    protected function extractMantisTicketsFromString($value)
    {
        $mantis_ticket_numbers = array();

        $matches = array();

        preg_match_all('~(?!mantis|refs|fixes|closes)\s+#?(\d+)~', $value, $matches, PREG_SET_ORDER);

        foreach ($matches as $match)
        {
            $mantis_ticket_numbers[] = $match[1];
        }

        return $mantis_ticket_numbers;
    }

    protected function createDatabaseIfNotExists()
    {
        if (!file_exists($this->database_file))
        {
            $sql_logentries = 'CREATE TABLE "logentries" (
                "revision" INT NOT NULL PRIMARY KEY,
                "msg" CLOB,
                "author" TEXT NOT NULL DEFAULT NULL,
                "date" TEXT DEFAULT NULL,
                "paths" CLOB
                )';

            $mantis_tickets_revisions = 'CREATE TABLE "mantis_tickets_revisions" (
                "mantis_ticket" INT NOT NULL,
                "revision" INT NOT NULL
                )';

            $mantis_tickets_revisions_idx = 'CREATE INDEX mantis_ticket_idx ON mantis_tickets_revisions (mantis_ticket)';

            $sql_revision = 'CREATE TABLE "revision" (
                "revision" INT NOT NULL)';

            $sql_initial_revision = 'INSERT INTO "revision" VALUES(0)';

            if ($db = sqlite_open($this->database_file, 0666, $sqlite_error))
            {
                sqlite_query($db, $sql_logentries);
                sqlite_query($db, $sql_revision);
                sqlite_query($db, $sql_initial_revision);
                sqlite_query($db, $mantis_tickets_revisions);
                sqlite_query($db, $mantis_tickets_revisions_idx);
                sqlite_close($db);
            }
            else
            {
                die($sqlite_error);
            }
        }
    }

    public function retrieveLogForMantis($mantis_ticket_number)
    {
        $items = array();
        $mantis_ticket_number = sqlite_escape_string($mantis_ticket_number);
        $query = $this->db->query('SELECT
                logentries.*
            FROM
                logentries
            LEFT JOIN
                mantis_tickets_revisions USING(revision)
            WHERE
                mantis_tickets_revisions.mantis_ticket = ' . sqlite_escape_string($mantis_ticket_number) . '
            ORDER BY
                logentries.revision DESC');

        $rows = $query->fetchAll(SQLITE_ASSOC);

        foreach ($rows as $row)
        {
            $items[] = array(
                    'revision' => $row['logentries.revision'],
                    'author' => $row['logentries.author'],
                    'date' => $row['logentries.date'],
                    'msg' => $row['logentries.msg'],
                    'paths' => json_decode($row['logentries.paths']),
            );
        }

        return $items;
    }
    
    protected function getSvnRevisionFromString($value, $key)
    {
        $matches = array();

        if (preg_match('~' . $key . '="(\d+)"~', $value, $matches))
        {
            return intval($matches[1]);
        }

        return 0;
    }

    protected function buildDatabaseFilenameByRepositoryUrl($repository_url)
    {
         return dirname(__FILE__) . '/../data/' . strtolower(preg_replace('~[^A-Za-z0-9]~', '_', $repository_url)) .'.mantissvnsqliterepository.db';
    }
}
