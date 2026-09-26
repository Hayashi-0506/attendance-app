<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\AttendanceListRequest;
use App\Http\Requests\User\EditAttendanceRequest;
use App\Http\Requests\User\StoreAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRequest;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\RequestService;
use Illuminate\Database\QueryException;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendanceService,
        private RequestService $requestService,
    ) {}

    /**
     * 勤怠入力画面
     */
    public function index()
    {
        $user = User::with('todayAttendance')->findOrFail(auth()->id());
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
        $this->attendanceService->createAttendanceRequest($request, $attendanceRecord);

        return redirect()->route('attendance.showAttendance', $attendanceRecord->id)
            ->with('success', '修正が完了しました。');
    }

    /**
     * 申請一覧
     */
    public function applicationList()
    {
        return $this->requestService->getApplicationList();
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
}
