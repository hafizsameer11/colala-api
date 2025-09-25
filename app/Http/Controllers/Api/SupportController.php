<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportMessageRequest;
use App\Http\Requests\SupportTicketRequest;
use App\Services\SupportService;
use Exception;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    //
     public function __construct(private SupportService $svc) {}

    public function createTicket(SupportTicketRequest $req) {
        try {
            $ticket = $this->svc->createTicket($req->validated(), $req->user()->id);
            return ResponseHelper::success($ticket,'Ticket created');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function listTickets(Request $req) {
        try {
            return ResponseHelper::success($this->svc->listTickets($req->user()->id));
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function getTicket($id, Request $req) {
        try {
            $ticket = $this->svc->getTicketWithMessages($id);
            $this->svc->markMessagesRead($id, $req->user()->id);
            return ResponseHelper::success($ticket);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function sendMessage(SupportMessageRequest $req) {
        try {
            $msg = $this->svc->sendMessage($req->validated(), $req->user()->id);
            return ResponseHelper::success($msg,'Message sent');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
}
