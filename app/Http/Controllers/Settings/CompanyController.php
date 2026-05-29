<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function edit(): Response
    {
        $logoPath = AppSetting::get('company_logo');

        return Inertia::render('settings/company', [
            'company_name' => AppSetting::get('company_name', 'Beulah Information Technology Services and Business Solutions Inc.'),
            'logo_url'     => $logoPath ? '/storage/' . $logoPath : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'logo'         => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,svg', 'max:2048'],
        ]);

        if ($request->filled('company_name')) {
            AppSetting::set('company_name', $request->input('company_name'));
        }

        if ($request->hasFile('logo')) {
            $oldLogo = AppSetting::get('company_logo');
            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }

            $path = $request->file('logo')->store('logos', 'public');
            AppSetting::set('company_logo', $path);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Company settings updated.']);

        return to_route('company.edit');
    }
}
