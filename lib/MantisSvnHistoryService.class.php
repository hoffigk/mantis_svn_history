<?php

class MantisSvnHistoryService
{

    protected $repository;

    public function __construct(MantisSvnHistoryBaseRepository $repository)
    {
        $this->repository = $repository;
    }

    public function retrieveLogForMantis($mantis_ticket_number)
    {
        return $this->repository->retrieveLogForMantis($mantis_ticket_number);
    }

}
