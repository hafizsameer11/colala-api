<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\SupportTicket;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\Store;
use App\Traits\PeriodFilterTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminSupportController extends Controller
{
    use PeriodFilterTrait;
    /**
     * Get all support tickets with filtering and pagination
     */
    public function getAllTickets(Request $request)
    {
        try {
            $query = SupportTicket::with(['user.store', 'messages']);

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                switch ($request->status) {
                    case 'pending':
                        $query->where('status', 'pending');
                        break;
                    case 'resolved':
                        $query->where('status', 'resolved');
                        break;
                    case 'closed':
                        $query->where('status', 'closed');
                        break;
                }
            }

            if ($request->has('priority') && $request->priority !== 'all') {
                $query->where('priority', $request->priority);
            }

            if ($request->has('issue_type') && $request->issue_type !== 'all') {
                $query->where('issue_type', $request->issue_type);
            }

            // Validate period parameter
            $period = $request->get('period');
            if ($period && !$this->isValidPeriod($period)) {
                return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
            }

            // Apply period filter (priority over date_range for backward compatibility)
            if ($period) {
                $this->applyPeriodFilter($query, $period);
            } elseif ($request->has('date_range')) {
                // Legacy support for date_range parameter
                switch ($request->date_range) {
                    case 'today':
                        $query->whereDate('created_at', today());
                        break;
                    case 'this_week':
                        $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                        break;
                    case 'this_month':
                        $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                        break;
                }
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($userQuery) use ($search) {
                          $userQuery->where('full_name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            $tickets = $query->latest()->paginate($request->get('per_page', 20));

            // Get summary statistics with period filtering
            $totalTicketsQuery = SupportTicket::query();
            $openTicketsQuery = SupportTicket::where('status', 'open');
            $pendingTicketsQuery = SupportTicket::where('status', 'pending');
            $resolvedTicketsQuery = SupportTicket::where('status', 'resolved');
            $closedTicketsQuery = SupportTicket::where('status', 'closed');
            
            if ($period) {
                $this->applyPeriodFilter($totalTicketsQuery, $period);
                $this->applyPeriodFilter($openTicketsQuery, $period);
                $this->applyPeriodFilter($pendingTicketsQuery, $period);
                $this->applyPeriodFilter($resolvedTicketsQuery, $period);
                $this->applyPeriodFilter($closedTicketsQuery, $period);
            }
            
            $stats = [
                'total_tickets' => $totalTicketsQuery->count(),
                'open_tickets' => $openTicketsQuery->count(),
                'pending_tickets' => $pendingTicketsQuery->count(),
                'resolved_tickets' => $resolvedTicketsQuery->count(),
                'closed_tickets' => $closedTicketsQuery->count(),
            ];

            return ResponseHelper::success([
                'tickets' => $this->formatTicketsData($tickets),
                'statistics' => $stats,
                'pagination' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(),
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get detailed ticket information
     */
    public function getTicketDetails($ticketId)
    {
        try {
            $ticket = SupportTicket::with([
                'user.store',
                'messages.sender'
            ])->findOrFail($ticketId);

            $ticketData = [
                'ticket_info' => [
                    'id' => $ticket->id,
                    'subject' => $ticket->subject,
                    'description' => $ticket->description,
                    'status' => $ticket->status,
                    'category' => $ticket->category,
                    'created_at' => $ticket->created_at,
                    'updated_at' => $ticket->updated_at,
                ],
                'user_info' => [
                    'user_id' => $ticket->user->id,
                    'full_name' => $ticket->user->full_name,
                    'email' => $ticket->user->email,
                    'phone' => $ticket->user->phone,
                    'role' => $ticket->user->role,
                    'profile_picture' => $ticket->user->role === 'seller' && $ticket->user->store && $ticket->user->store->profile_image 
                        ? asset('storage/' . $ticket->user->store->profile_image) 
                        : ($ticket->user->profile_picture ? asset('storage/' . $ticket->user->profile_picture) : null),
                ],
                'messages' => $ticket->messages->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'message' => $message->message,
                        'sender_id' => $message->sender_id,
                        'user_name' => $message->sender ? $message->sender->full_name : 'System',
                        'is_read' => $message->is_read,
                        'created_at' => $message->created_at,
                        'formatted_date' => $message->created_at->format('d-m-Y H:i A'),
                    ];
                }),
                'ticket_statistics' => [
                    'total_messages' => $ticket->messages->count(),
                    'unread_messages' => $ticket->messages->where('is_read', false)->count(),
                ],
            ];

            return ResponseHelper::success($ticketData);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Reply to ticket
     */
    public function replyToTicket(Request $request, $ticketId)
    {
        try {
            $request->validate([
                'message' => 'required|string|max:1000',
            ]);

            $ticket = SupportTicket::findOrFail($ticketId);

            $message = SupportMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $request->user()->id,
                'message' => $request->message,
                'is_read' => false,
            ]);

            // Update ticket status to pending when admin replies
            $ticket->update(['status' => 'pending']);

            return ResponseHelper::success([
                'message_id' => $message->id,
                'ticket_id' => $ticket->id,
                'message' => $message->message,
                'sender_id' => $message->sender_id,
                'created_at' => $message->created_at,
            ], 'Reply sent successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update ticket status
     */
    public function updateTicketStatus(Request $request, $ticketId)
    {
        try {
            $request->validate([
                'status' => 'required|in:open,pending,resolved,closed',
            ]);

            $ticket = SupportTicket::findOrFail($ticketId);
            
            $ticket->update([
                'status' => $request->status,
            ]);

            return ResponseHelper::success([
                'ticket_id' => $ticket->id,
                'status' => $ticket->status,
                'updated_at' => $ticket->updated_at,
            ], 'Ticket status updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Mark ticket as resolved
     */
    public function resolveTicket($ticketId)
    {
        try {
            $ticket = SupportTicket::findOrFail($ticketId);
            
            $ticket->update(['status' => 'resolved']);

            return ResponseHelper::success([
                'ticket_id' => $ticket->id,
                'status' => 'resolved',
                'updated_at' => $ticket->updated_at,
            ], 'Ticket resolved successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Close ticket
     */
    public function closeTicket($ticketId)
    {
        try {
            $ticket = SupportTicket::findOrFail($ticketId);
            
            $ticket->update(['status' => 'closed']);

            return ResponseHelper::success([
                'ticket_id' => $ticket->id,
                'status' => 'closed',
                'updated_at' => $ticket->updated_at,
            ], 'Ticket closed successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Delete ticket
     */
    public function deleteTicket($ticketId)
    {
        try {
            $ticket = SupportTicket::findOrFail($ticketId);
            
            // Delete all messages first
            $ticket->messages()->delete();
            $ticket->delete();

            return ResponseHelper::success(null, 'Ticket deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get support ticket statistics
     */
    public function getSupportTicketStatistics(Request $request)
    {
        try {
            // Validate period parameter
            $period = $request->get('period');
            if ($period && !$this->isValidPeriod($period)) {
                return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
            }
            
            $totalTicketsQuery = SupportTicket::query();
            $openTicketsQuery = SupportTicket::where('status', 'open');
            $pendingTicketsQuery = SupportTicket::where('status', 'pending');
            $resolvedTicketsQuery = SupportTicket::where('status', 'resolved');
            $closedTicketsQuery = SupportTicket::where('status', 'closed');
            
            if ($period) {
                $this->applyPeriodFilter($totalTicketsQuery, $period);
                $this->applyPeriodFilter($openTicketsQuery, $period);
                $this->applyPeriodFilter($pendingTicketsQuery, $period);
                $this->applyPeriodFilter($resolvedTicketsQuery, $period);
                $this->applyPeriodFilter($closedTicketsQuery, $period);
            }
            
            return ResponseHelper::success([
                'total_tickets' => $totalTicketsQuery->count(),
                'open_tickets' => $openTicketsQuery->count(),
                'pending_tickets' => $pendingTicketsQuery->count(),
                'resolved_tickets' => $resolvedTicketsQuery->count(),
                'closed_tickets' => $closedTicketsQuery->count(),
            ], 'Support ticket statistics retrieved successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get support analytics
     */
    public function getSupportAnalytics(Request $request)
    {
        try {
            // Validate period parameter
            $period = $request->get('period');
            if ($period && !$this->isValidPeriod($period)) {
                return ResponseHelper::error('Invalid period parameter. Valid values: today, this_week, this_month, last_month, this_year, all_time', 422);
            }
            
            $dateRange = $this->getDateRange($period);
            
            // Use period if provided, otherwise fall back to date_from/date_to
            if ($dateRange) {
                $dateFrom = $dateRange['start']->format('Y-m-d');
                $dateTo = $dateRange['end']->format('Y-m-d');
            } else {
                $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
                $dateTo = $request->get('date_to', now()->format('Y-m-d'));
            }

            // Ticket trends
            $ticketTrends = SupportTicket::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_tickets,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_tickets,
                SUM(CASE WHEN status = "resolved" THEN 1 ELSE 0 END) as resolved_tickets,
                SUM(CASE WHEN status = "closed" THEN 1 ELSE 0 END) as closed_tickets
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // Message statistics
            $messageStats = SupportMessage::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_messages
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // Response time analytics
            $responseTimeStats = SupportTicket::selectRaw('
                AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_response_time_hours,
                AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_response_time_minutes
            ')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('status', 'resolved')
            ->first();

            return ResponseHelper::success([
                'ticket_trends' => $ticketTrends,
                'message_stats' => $messageStats,
                'response_time_stats' => $responseTimeStats,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Format tickets data for response
     */
    private function formatTicketsData($tickets)
    {
        return $tickets->map(function ($ticket) {
            // Determine profile picture based on user role
            $profilePicture = null;
            if ($ticket->user->role === 'seller' && $ticket->user->store && $ticket->user->store->profile_image) {
                $profilePicture = asset('storage/' . $ticket->user->store->profile_image);
            } elseif ($ticket->user->profile_picture) {
                $profilePicture = asset('storage/' . $ticket->user->profile_picture);
            }

            return [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'status' => $ticket->status,
                'category' => $ticket->category,
                'user_name' => $ticket->user->full_name,
                'user_email' => $ticket->user->email,
                'user_profile_picture' => $profilePicture,
                'messages_count' => $ticket->messages->count(),
                'unread_messages' => $ticket->messages->where('is_read', false)->count(),
                'created_at' => $ticket->created_at,
                'formatted_date' => $ticket->created_at->format('d-m-Y H:i A'),
            ];
        });
    }
}
