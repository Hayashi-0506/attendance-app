<?php

namespace App\Http\Controllers;

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

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        // is_adminでない場合は、パスワードが合っていても認証させない
        if (! $user || ! $user->is_admin || ! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'ログイン情報が登録されていません。',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dailyAttendanceList'));
    }

    public function dailyAttendanceList(Request $request)
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

    public function staffAttendanceList(Request $request, User $user)
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
}
