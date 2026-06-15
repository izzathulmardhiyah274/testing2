<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssessmentScoreController extends Controller
{
    /**
     * Pastikan komponen penilaian ini milik kelas yang diajar dosen yang login.
     * Mencegah dosen mengakses atau menimpa nilai kelas lain (IDOR).
     */
    private function authorizeAssessment(Assessment $assessment): Classroom
    {
        $classroom = $assessment->classroom_id
            ? Classroom::find($assessment->classroom_id)
            : null;

        abort_if($classroom === null, 404, 'Komponen penilaian tidak terkait kelas mana pun.');
        abort_unless($classroom->isTaughtBy(Auth::user()), 403, 'Anda tidak mengajar kelas ini.');

        return $classroom;
    }

    public function index(Assessment $assessment, Request $request)
    {
        $assessment->load('indicator.cpmk.cpl', 'indicator.cpmk.course', 'scores');

        $classroom = $this->authorizeAssessment($assessment);

        // Hanya mahasiswa yang terdaftar di kelas komponen ini.
        $students = $classroom->students()
            ->where('role', 'mahasiswa')
            ->orderBy('identity')
            ->get(['obe_pengguna.id', 'name', 'identity']);

        $scores = $assessment->scores()->pluck('score', 'student_id');

        return view('dosen.assessments.show', compact('assessment', 'students', 'scores', 'classroom'));
    }

    public function store(Assessment $assessment, Request $request)
    {
        $classroom = $this->authorizeAssessment($assessment);

        $data = $request->validate([
            'scores' => 'array',
            'scores.*' => 'nullable|numeric|min:0|max:100',
        ]);

        // Batasi penyimpanan hanya untuk mahasiswa yang benar-benar terdaftar di
        // kelas ini — cegah injeksi student_id sembarangan dari request.
        $enrolledIds = $classroom->students()->pluck('obe_pengguna.id')->all();

        foreach ($data['scores'] ?? [] as $studentId => $score) {
            if (! in_array((int) $studentId, $enrolledIds, true)) {
                continue;
            }

            if ($score !== null && $score !== '') {
                AssessmentScore::updateOrCreate(
                    ['assessment_id' => $assessment->id, 'student_id' => $studentId],
                    ['score' => $score]
                );
            } else {
                AssessmentScore::where('assessment_id', $assessment->id)
                    ->where('student_id', $studentId)
                    ->delete();
            }
        }

        return redirect()->back()->with('success', 'Nilai berhasil disimpan.');
    }
}
