<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Libraries\EmailLib;
use Mail;

class ProcessEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userDetail;
    
    /**
    * Create a new job instance.
    *
    * @return void
    */
    public function __construct($userDetail)
    {
        $this->userDetail = $userDetail;
    }

    /**
    * Execute the job.
    *
    * @return void
    */
    public function handle()
    {
        Mail::to($this->userDetail['to'])->send(new EmailLib($this->userDetail));
    }
}
