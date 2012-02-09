<?php

require_once 'MantisSvnHistoryBaseRepository.class.php';

class MantisSvnHistoryXmlRepository extends MantisSvnHistoryBaseRepository
{

    protected $lock_file_path;

    protected $cache_file;

    public function __construct($repository_url, $username = null, $password = null)
    {
        $this->svn_repository = $repository_url;
        $this->svn_username = $username;
        $this->svn_password = $password;

        $this->lock_file_path = dirname(__FILE__) . '/../data/MantisSvnXmlRepository.lock';
        $this->cache_file = dirname(__FILE__) . '/../data/MantisSvnXmlRepository.cache.xml';

        $this->buildCacheFile();
    }

    public function retrieveLogForMantis($mantis_ticket_number)
    {
        $items = array();

        if (!file_exists($this->cache_file))
        {
            return $items;
        }

        $xml = simplexml_load_file($this->cache_file);

        foreach ($xml->logentry as $logentry)
        {
            $msg = (string)$logentry[0]->msg;

            $match = false;

            if (strpos($msg, "refs #{$mantis_ticket_number}") !== false)
            {
                $match = true;
            }

            if (!$match && strpos($msg, "mantis #{$mantis_ticket_number}") !== false)
            {
                $match = true;
            }

            if (!$match && strpos($msg, "mantis {$mantis_ticket_number}") !== false)
            {
                $match = true;
            }

            if ($match)
            {
                $paths = array();
                foreach ($logentry->paths->path as $path)
                {
                    $paths[] = array(
                            'path' => (string)$path,
                            'action' => (string)$path['action']
                    );
                }

                $items[] = array(
                        'revision' => (string)$logentry[revision],
                        'author' => (string)$logentry->author,
                        'date' => gmdate('D, d M Y H:i:s', strtotime($logentry->date) + date('Z')),
                        'msg' => (string)$logentry->msg,
                        'paths' => $paths,
                );
            }
        }

        return $items;
    }

    protected function getLatestRevision()
    {
        $handle = fopen($this->cache_file, 'r');
        $contents = fread($handle, 64);
        fclose($handle);

        return $this->getSvnRevisionFromString($contents, 'latest_revision');
    }

    protected function buildCacheFile()
    {
        if ($this->isLocked())
        {
            return;
        }
        $this->lock();

        $initial_svn_revision = $this->formatSvnRevisionsNumber(0);

        if (!file_exists($this->cache_file))
        {
            file_put_contents($this->cache_file, '<log latest_revision="' . $initial_svn_revision . '"></log>');
        }

        $latest_revision = $this->getLatestRevision();

        $log_entries_as_string = $this->retrieveSvnLogEntriesAsString($latest_revision);

        if (empty($log_entries_as_string))
        {
            return;
        }

        $new_latest_revision = $this->getSvnRevisionFromString($log_entries_as_string, 'revision');

        if ($new_latest_revision != $latest_revision)
        {
            $handle = fopen($this->cache_file, 'r+');

            fseek($handle, 22);
            fwrite($handle, $this->formatSvnRevisionsNumber($new_latest_revision));

            fseek($handle, -6, SEEK_END);
            fwrite($handle, $log_entries_as_string . '</log>');

            fclose($handle);
        }

        $this->unlock();
    }

    protected function formatSvnRevisionsNumber($number)
    {
        return sprintf("%011d", $number);
    }

    protected function lock()
    {
        if (!$this->isLocked())
        {
            file_put_contents($this->lock_file_path, getmypid());
        }
    }

    protected function unlock()
    {
        if (is_file($this->lock_file_path))
        {
            unlink($this->lock_file_path);
        }
    }

    protected function isLocked()
    {
        if (is_file($this->lock_file_path))
        {
            $pid = trim(file_get_contents($this->lock_file_path));

            if (function_exists('posix_kill'))
            {
                $running = posix_kill($pid, 0);
            }
            else
            {
                $running = false;
                $commands = array();
                exec("ps -p $pid", $commands);
                $running = isset($commands[1]) && strpos($commands[1], $pid) !== false;
            }

            if ($running == false)
            {
                $this->unlock();
                return false;
            }
            else
            {
                return true;
            }
        }

        return false;
    }

}
