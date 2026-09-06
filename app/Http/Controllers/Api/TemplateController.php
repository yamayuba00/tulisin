<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    /**
     * Daftar template kustom milik pengguna yang sedang login.
     */
    public function index(Request $request): JsonResponse
    {
        $templates = Template::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Template $t) => $this->present($t));

        return response()->json(['templates' => $templates]);
    }

    /**
     * Simpan template kustom baru.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $template = Template::create([
            'user_id' => $request->user()->id,
            'name' => $data['title'],
            'category' => $data['category'] ?? 'Custom',
            'description' => $data['description'] ?? null,
            'format' => $data['format'] ?? 'A4',
            'font' => $data['font'] ?? 'Times New Roman',
            'blocks' => $data['blocks'],
        ]);

        return response()->json(['template' => $this->present($template)], 201);
    }

    /**
     * Hapus template kustom milik pengguna.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $template = Template::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $template) {
            return response()->json(['error' => 'Template tidak ditemukan.'], 404);
        }

        $template->delete();

        return response()->json(['message' => 'Template dihapus.']);
    }

    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:500'],
            'format' => ['nullable', 'string', 'max:20'],
            'font' => ['nullable', 'string', 'max:100'],
            'blocks' => ['required', 'array'],
        ];
    }

    private function present(Template $template): array
    {
        return [
            'id' => $template->uuid,
            'title' => $template->name,
            'category' => $template->category,
            'description' => $template->description,
            'format' => $template->format,
            'font' => $template->font,
            'blocks' => $template->blocks ?? [],
            'custom' => true,
        ];
    }
}
