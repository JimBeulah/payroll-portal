<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreHolidayRequest;
use App\Http\Requests\UpdateHolidayRequest;
use App\Models\Holiday;
use Inertia\Inertia;

class HolidayController extends Controller
{
    public function index()
    {
        return Inertia::render('holidays/index', [
            'holidays' => Holiday::orderBy('date')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('holidays/create');
    }

    public function store(StoreHolidayRequest $request)
    {
        Holiday::create($request->validated());
        return redirect('/holidays')->with('success', 'Holiday added.');
    }

    public function edit(Holiday $holiday)
    {
        return Inertia::render('holidays/edit', ['holiday' => $holiday]);
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday)
    {
        $holiday->update($request->validated());
        return redirect('/holidays')->with('success', 'Holiday updated.');
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();
        return redirect('/holidays')->with('success', 'Holiday deleted.');
    }
}
