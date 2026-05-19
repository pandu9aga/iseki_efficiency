<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiAnalysisService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AiInsightController extends Controller
{
    /**
     * Tampilkan halaman AI Insight Analytics
     */
    public function index(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $date = $request->filled('date')
            ? Carbon::parse($request->date)->format('Y-m-d')
            : Carbon::today()->format('Y-m-d');

        return view('admins.ai-insight', compact('date'));
    }

    /**
     * API endpoint: Generate AI analysis via AJAX
     * Dipisah dari page load agar halaman tetap responsif
     */
    public function analyze(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $date = $request->filled('date')
            ? Carbon::parse($request->date)->format('Y-m-d')
            : Carbon::today()->format('Y-m-d');

        $service = new AiAnalysisService();

        // Gather metrics (untuk ditampilkan sebagai data card)
        $metrics = $service->gatherMetrics($date);

        // Generate AI insight (dikirim ke Groq)
        $insight = $service->generateDailyInsight($date);

        return response()->json([
            'success' => true,
            'date' => $date,
            'metrics' => $metrics,
            'insight' => $insight,
        ]);
    }
}
