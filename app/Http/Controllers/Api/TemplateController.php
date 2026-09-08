<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

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
     * Daftar template publik dari pengguna lain (marketplace), lengkap dengan
     * nama pembuat, harga, dan bagian koin untuk pembuat.
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $templates = Template::query()
            ->where('user_id', '!=', $request->user()->id)
            ->with('user:id,name')
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
            'price' => (int) ($data['price'] ?? 8),
            'creator_share' => (int) ($data['creator_share'] ?? 2),
        ]);

        return response()->json(['template' => $this->present($template)], 201);
    }

    /**
     * Beli & pakai template milik pengguna lain. Potong harga (koin) dari
     * pembeli, lalu bagikan creator_share ke pembuat template.
     */
    public function use(Request $request, string $uuid): JsonResponse
    {
        $template = Template::where('uuid', $uuid)->first();

        if (! $template) {
            return response()->json(['error' => 'Template tidak ditemukan.'], 404);
        }

        if ($template->user_id === $request->user()->id) {
            return response()->json(['error' => 'Ini template milikmu sendiri, tidak perlu dibeli.'], 422);
        }

        $price = (int) ($template->price ?? 8);
        $creatorShare = (int) ($template->creator_share ?? 2);

        $buyerWallet = Wallet::firstOrCreate(['user_id' => $request->user()->id]);

        try {
            $buyerWallet->debit($price, 'template_purchase', 'Template', $template->id);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        if ($creatorShare > 0) {
            $creatorWallet = Wallet::firstOrCreate(['user_id' => $template->user_id]);
            $creatorWallet->credit($creatorShare, 'template_sale', 'Template', $template->id);
        }

        return response()->json([
            'message' => 'Template berhasil dibeli.',
            'template' => $this->present($template),
            'balance' => $buyerWallet->balance,
        ]);
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
            'price' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'creator_share' => ['sometimes', 'integer', 'min:0', 'max:100000'],
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
            'author' => $template->user?->name ?? 'Anonim',
            'price' => (int) ($template->price ?? 8),
            'creator_share' => (int) ($template->creator_share ?? 2),
        ];
    }
}
