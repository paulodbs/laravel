<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    /**
     * Display a listing of tickets (admin)
     */
    public function index(Request $request)
    {
        $query = Ticket::with(['user', 'assignedUser'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by assigned user
        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $perPage = min($request->get('per_page', 15), 50);
        $tickets = $query->paginate($perPage);

        return response()->json($tickets);
    }

    /**
     * Store a newly created ticket
     */
    public function store(Request $request)
    {
        $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
        ]);

        $ticket = Ticket::create([
            'user_id' => $request->user()->id,
            'subject' => $request->subject,
            'message' => $request->message,
            'priority' => $request->priority ?? 'medium',
            'status' => 'open',
        ]);

        $ticket->load('user');

        return response()->json([
            'message' => 'Ticket created successfully',
            'ticket' => $ticket
        ], 201);
    }

    /**
     * Get tickets for a specific user
     */
    public function userTickets(Request $request, $userId)
    {
        // Check if user can access these tickets
        if (!$request->user()->isAdmin() && $userId != $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Ticket::where('user_id', $userId)
            ->with('assignedUser')
            ->orderBy('created_at', 'desc');

        $perPage = min($request->get('per_page', 15), 50);
        $tickets = $query->paginate($perPage);

        return response()->json($tickets);
    }

    /**
     * Update the specified ticket (admin only)
     */
    public function update(Request $request, Ticket $ticket)
    {
        $request->validate([
            'status' => ['sometimes', 'in:open,in_progress,resolved,closed'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'],
        ]);

        $data = $request->only(['status', 'priority', 'assigned_to']);

        // Handle status changes
        if (isset($data['status'])) {
            switch ($data['status']) {
                case 'resolved':
                    $data['resolved_at'] = now();
                    break;
                case 'in_progress':
                    if (!isset($data['assigned_to']) && !$ticket->assigned_to) {
                        $data['assigned_to'] = $request->user()->id;
                    }
                    break;
            }
        }

        // Auto-assign if assigned_to is provided
        if (isset($data['assigned_to']) && $ticket->status === 'open') {
            $data['status'] = 'in_progress';
        }

        $ticket->update($data);
        $ticket->load(['user', 'assignedUser']);

        return response()->json([
            'message' => 'Ticket updated successfully',
            'ticket' => $ticket
        ]);
    }
}
