<?php

abstract class MantisSvnHistoryBaseRepository
{

    protected $svn_repository;
    protected $svn_username;
    protected $svn_password;

    abstract function retrieveLogForMantis($mantis_ticket_number);

    protected function getSvnRevisionFromString($value, $key)
    {
        $matches = array();

        if (preg_match('~' . $key . '="(\d+)"~', $value, $matches))
        {
            return intval($matches[1]);
        }

        return 0;
    }

    protected function retrieveSvnLogEntriesAsString($latest_revision, $incremental = true)
    {
        $incremental = ($incremental) ? '--incremental' : '';
        
        $svn_login_credentials = '';
        
        if ($this->hasSvnLoginCredentials())
        {
            $svn_login_credentials = sprintf('--username %1$s --password %2$s', escapeshellarg($this->svn_username), escapeshellarg($this->svn_password));
        }
        
        $latest_revision = escapeshellarg($latest_revision);
        $svn_repository = escapeshellarg($this->svn_repository);
        
        $cmd = "svn log {$svn_login_credentials} {$svn_repository} -rHEAD:{$latest_revision} {$incremental} --verbose --xml";

        exec($cmd, $output);

        return implode("", $output);
    }
    
    protected function hasSvnLoginCredentials()
    {
        return !empty($this->svn_username) && !empty($this->svn_password);
    }
}
