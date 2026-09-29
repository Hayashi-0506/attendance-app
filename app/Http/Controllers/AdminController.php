<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\DateRequest;
use App\Http\Requests\Admin\ExportAttendanceRequest;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Requests\User\EditAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRequest;
use App\Models\User;
use App\Services\AdminService;
use App\Services\RequestService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function __construct(
        private AdminService $adminService,
        private RequestService $requestService,
    ) {}

    public function create()
    {
        return view('admin.admin-login');
    }

    public function store(UserRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        // is_adminでない場合は、パスワードが合っていても認証させない
        if (! $user || ! $user->is_admin || ! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'ログイン情報が登録されていません',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dailyAttendanceList'));
    }

    public function dailyAttendanceList(DateRequest $request)
    {
        $date = $request->query('date') ? CarbonImmutable::createFromFormat('Y-m-d', $request->query('date')) : now()->toImmutable();
        $attendanceRecords = AttendanceRecord::with('user', 'breakRecords')
            ->where('date', $date->toDateString())
            ->get();
        $users = User::all();

        return view('admin.admin-attendance-list', [
            'date' => $date,
            'previousDay' => $date->modify('-1 day')->format('Y-m-d'),
            'nextDay' => $date->modify('1 day')->format('Y-m-d'),
            'users' => $users,
            'attendanceRecords' => $attendanceRecords,
        ]);
    }

    public function staffList()
    {
        $users = User::all();

        return view('admin.staff-list', ['users' => $users]);
    }

    public function staffAttendanceList(DateRequest $request, User $user)
    {
        $date = $this->adminService->resolveTargetMonth($request->query('date'));
        $formattedAttendanceRecords = $this->adminService->getMonthlyAttendanceRecords($user, $date);

        return view('admin.staff-attendance-list', [
            'date' => $date,
            'user' => $user,
            'previousMonth' => $date->subMonth()->format('Y-m'),
            'nextMonth' => $date->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $formattedAttendanceRecords,
        ]);
    }

    public function showAttendance(AttendanceRecord $attendanceRecord)
    {
        $data = $this->adminService->getAttendanceDetail($attendanceRecord);
        $user = User::find($attendanceRecord->user_id);

        return view('admin.admin-detail', [
            'attendanceRecord' => $data,
            'user' => $user,
        ]);
    }

    public function editAttendance(EditAttendanceRequest $request, AttendanceRecord $attendanceRecord)
    {
        // AttendanceRecordを更新します。
        $this->adminService->updateAttendanceRecord($request, $attendanceRecord);

        // 承認済みのAttendanceRequestを作成します。
        $this->adminService->createAttendanceRequest($request, $attendanceRecord);

        return redirect()->route('admin.showAttendance', $attendanceRecord->id)
            ->with('success', '修正が完了しました。');
    }

    public function showRequest(AttendanceRequest $attendanceRequest)
    {
        $application = $this->requestService->getApplicationDetail($attendanceRequest);

        return view('admin.admin-application-detail', [
            'user' => $attendanceRequest->user,
            'application' => $application,
        ]);
    }

    public function approveRequest(AttendanceRequest $attendanceRequest)
    {
        $this->adminService->approveAttendanceRequest($attendanceRequest);

        return redirect()->route('admin.showRequest', $attendanceRequest->id)
            ->with('success', '承認しました。');

    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function export(ExportAttendanceRequest $request)
    {
        $user = User::find($request['user_id']);
        $date = $this->adminService->resolveTargetMonth($request['year_month']);

        $attendanceRecords = $this->adminService->getMonthlyAttendanceRecords($user, $date);

        return response()->streamDownload(function () use ($attendanceRecords) {
            $handle = fopen('php://output', 'w');
            // BOMを追加（Excel対応）
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['日付', '出勤', '退勤', '休憩', '合計']);
            foreach ($attendanceRecords as $attendanceRecord) {
                fputcsv($handle, [
                    $attendanceRecord['date'],
                    $attendanceRecord['clock_in'],
                    $attendanceRecord['clock_out'],
                    $attendanceRecord['total_break_time'],
                    $attendanceRecord['total_time'],
                ]);
            }
            fclose($handle);
        }, 'attendanceRecords_'.now()->format('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
