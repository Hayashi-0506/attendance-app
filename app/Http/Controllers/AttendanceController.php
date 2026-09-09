<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\AttendanceListRequest;
use App\Http\Requests\User\EditAttendanceRequest;
use App\Http\Requests\User\StoreAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRequest;
use App\Models\User;
use App\Services\AttendanceService;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendanceService,
    ) {}

    /**
     * 勤怠入力画面
     */
    public function index()
    {
        $user = User::with('todayAttendance')
            ->findOrFail(auth()->id());

        $formattedDate = date('Y-m-d');
        $formattedTime = date('H:i:s');

        return view('user.attendance-register', compact('user', 'formattedDate', 'formattedTime'));
    }

    /**
     * 勤怠入力：出勤、退勤、休憩入、休憩出
     */
    public function store(StoreAttendanceRequest $request)
    {
        try {
            match ($request->input('action')) {
                'clock_in' => $this->attendanceService->clockIn(auth()->user()),
                'clock_out' => $this->attendanceService->clockOut(auth()->user()),
                'break_in' => $this->attendanceService->startBreak(auth()->user()),
                'break_out' => $this->attendanceService->endBreak(auth()->user()),
            };
        } catch (QueryException $e) {
            abort(422, '既に処理済みか、リクエストが競合しました。画面を更新してもう一度お試しください。');
        }

        return redirect()->back();
    }

    /**
     * 勤怠一覧
     */
    public function attendanceList(AttendanceListRequest $request)
    {
        $date = $this->attendanceService->resolveTargetMonth($request->query('date'));
        $formattedAttendanceRecords = $this->attendanceService->getMonthlyAttendanceRecords(auth()->user(), $date);

        return view('user.user-attendance-list', [
            'date' => $date,
            'previousMonth' => $date->subMonth()->format('Y-m'),
            'nextMonth' => $date->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $formattedAttendanceRecords,
        ]);
    }

    /**
     * 勤怠詳細/編集画面
     */
    public function showAttendance(AttendanceRecord $attendanceRecord)
    {
        abort_if($attendanceRecord->user_id !== auth()->id(), 403);

        $data = $this->attendanceService->getAttendanceDetail($attendanceRecord);

        return view('user.user-detail', [
            'data' => $data,
            'user' => auth()->user(),
        ]);
    }

    /**
     * 勤怠修正処理
     */
    public function edit(EditAttendanceRequest $request, AttendanceRecord $attendanceRecord)
    {
        $date = new DateTimeImmutable($attendanceRecord->date);

        $attendanceRequest = AttendanceRequest::create([
            'attendance_record_id' => $attendanceRecord->id,
            'user_id' => auth()->id(),
            'clock_in' => $date->modify($request->new_clock_in),
            'clock_out' => $date->modify($request->new_clock_out),
            'comment' => $request->comment,
        ]);

        foreach ($request->breaks as $break) {
            if ($break['new_break_in']) {
                $attendanceRequest->breakRequests()->create([
                    'attendance_request_id' => $attendanceRequest->id,
                    'break_in' => $break['new_break_in'],
                    'break_out' => $break['new_break_out'],
                ]);
            }
        }

        redirect()->route('attendance.showAttendance', ['id' => $attendanceRecord->id])
            ->with('success', '修正が完了しました。');
    }

    /**
     * 申請一覧
     */
    public function applicationList()
    {
        $user = auth()->user();
        $attendanceRequests = AttendanceRequest::with('attendanceRecord', 'breakRequests')
            ->where('user_id', $user->id)
            ->get();

        $formattedApplications = $attendanceRequests->map(fn ($attendanceRequest) => [
            'id' => $attendanceRequest->id,
            'approval_status' => $attendanceRequest->approval_status->label(),
            'date' => $attendanceRequest->attendanceRecord->date->format('Y/m/d'),
            'comment' => $attendanceRequest->comment,
            'application_date' => $attendanceRequest->created_at->format('Y/m/d'),
        ])->toArray();

        return view('user.user-application-list', [
            'user' => $user,
            'formattedApplications' => $formattedApplications,
        ]);
    }

    /**
     * 勤怠詳細/編集画面
     */
    public function showApplication(AttendanceRequest $attendanceRequest)
    {
        abort_if($attendanceRequest->user_id !== auth()->id(), 403);

        $data = $this->attendanceService->getApplicationDetail($attendanceRequest);

        return view('user.user-detail', [
            'data' => $data,
            'user' => auth()->user(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        dd($request);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
