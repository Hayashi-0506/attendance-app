<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Illuminate\Support\Carbon;

class AttendanceRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexAttendanceRecordRequest $request)
    {
        $query = AttendanceRecord::with(['user', 'breakRecords']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->filled('month')) {
            $start = Carbon::createFromFormat('Y-m', $request->month)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $query->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
        }

        $perPage = $request->input('per_page', 20);

        $query->orderBy('user_id', 'asc');
        $query->orderBy('id', 'asc');

        $attendanceRecords = $query->latest()->paginate($perPage);

        return AttendanceRecordResource::collection($attendanceRecords);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAttendanceRecordRequest $request)
    {
        $data = $request->validated();

        $data['clock_in'] = "{$data['date']} {$data['clock_in']}";
        if (! empty($data['clock_out'])) {
            $data['clock_out'] = "{$data['date']} {$data['clock_out']}";
        }

        $record = $request->user()->attendanceRecords()->create($data);

        return response()->json($record, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(AttendanceRecord $attendanceRecord)
    {
        $attendanceRecord->load(['user', 'breakRecords', 'attendanceRequests.breakRequests']);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAttendanceRecordRequest $request, AttendanceRecord $attendanceRecord)
    {
        $this->authorize('update', $attendanceRecord);

        $data = $request->validated();
        $date = Carbon::parse($attendanceRecord->date)->format('Y-m-d');

        if (isset($data['clock_in'])) {
            $data['clock_in'] = "{$date} {$data['clock_in']}";
        }
        if (! empty($data['clock_out'])) {
            $data['clock_out'] = "{$date} {$data['clock_out']}";
        }

        $attendanceRecord->update($data);

        return response()->json($attendanceRecord);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AttendanceRecord $attendanceRecord)
    {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->noContent();
    }
}
