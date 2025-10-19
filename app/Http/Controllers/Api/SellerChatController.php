<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Services\SellerChatService;
// use App\Services\Seller\SellerChatService;
use Exception;
use Illuminate\Http\Request;

class SellerChatController extends Controller
{
    public function __construct(private SellerChatService $svc) {}

    public function list(Request $req)
    {
        try {
            $chats = $this->svc->fetchChatList($req->user()->id);
            return ResponseHelper::success($chats);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function messages(Request $req, $chatId)
    {
        try {
            $messages = $this->svc->fetchMessages((int)$chatId, $req->user()->id);
            return ResponseHelper::success($messages);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function send(SendMessageRequest $req, $chatId)
    {
        try {
            //check atelast message or image must be present
            if(!$req->has('message') && !$req->hasFile('image')) {
                return ResponseHelper::error('At least one message or image must be present', 400);
            }
            $msg = $this->svc->sendMessage(
                (int)$chatId,
                $req->user()->id,
                $req->input('message'),
                $req->file('image')
            );
            return ResponseHelper::success($msg, 'Message sent');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
