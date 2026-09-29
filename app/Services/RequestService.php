<?php

namespace App\Services;

use App\Models\AttendanceRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class RequestService
{
    public function getApplicationList(): View
    {
        return auth()->user()->is_admin
            ? $this->listForAdmin()
            : $this->listForUser();
    }

    private function listForAdmin(): View
    {
        $attendanceRequests = AttendanceRequest::with('user', 'attendanceRecord', 'breakRequests')
            ->get();

        return view('admin.admin-application-list', [
            'applications' => $this->getAttendanceRequestArray($attendanceRequests),
        ]);
    }

    private function listForUser(): View
    {
        $user = auth()->user();
        $attendanceRequests = AttendanceRequest::with('attendanceRecord', 'breakRequests')
            ->where('user_id', $user->id)
            ->get();

        return view('user.user-application-list', [
            'user' => $user,
            'formattedApplications' => $this->getAttendanceRequestArray($attendanceRequests),
        ]);
    }

    private function getAttendanceRequestArray(Collection $attendanceRequests)
    {
        return $attendanceRequests->map(fn ($attendanceRequest) => [
            'id' => $attendanceRequest->id,
            'approval_status' => $attendanceRequest->request_status,
            'date' => $attendanceRequest->attendanceRecord->date->format('Y/m/d'),
            'comment' => $attendanceRequest->comment,
            'application_date' => $attendanceRequest->created_at->format('Y/m/d'),
            'user_name' => $attendanceRequest->user->name,
        ])->toArray();
    }

    public function getApplicationDetail(AttendanceRequest $request)
    {
        $request->load(['user', 'breakRequests']);

        return (object) [
            'id' => $request->id,
            'new_date' => $request->date,
            'new_clock_in' => $request->clock_in?->format('H:i'),
            'new_clock_out' => $request->clock_out?->format('H:i'),
            'approval_status' => $request->request_status,
            'comment' => $request->comment,
            'proposalBreaks' => $request->breakRequests->map(fn ($break) => (object) [
                'break_in' => $break->break_in?->format('H:i'),
                'break_out' => $break->break_out?->format('H:i'),
            ]),
        ];
    }
}
