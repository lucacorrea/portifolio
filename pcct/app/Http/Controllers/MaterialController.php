<?php

namespace App\Http\Controllers;

use App\Models\Disciplina;
use App\Models\HabilidadeBncc;
use App\Models\ObjetoAprendizagem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MaterialController extends Controller
{
    private function professorId(): int
    {
        $id = DB::table('professores')->where('user_id', Auth::id())->value('id');
        abort_unless($id, 403, 'Seu perfil de professor ainda não está configurado.');
        return (int) $id;
    }

    private function authorizeMaterial(ObjetoAprendizagem $material): void
    {
        abort_unless($material->professor_id === $this->professorId(), 403);
    }

    public function index()
    {
        return view('professor.materiais.index', [
            'materiais' => ObjetoAprendizagem::with('disciplina')
                ->where('professor_id', $this->professorId())->latest()->paginate(10),
        ]);
    }

    private function formData(ObjetoAprendizagem $material): array
    {
        return [
            'material' => $material->load('habilidades'),
            'disciplinas' => Disciplina::orderBy('nome')->get(),
            'habilidades' => HabilidadeBncc::orderBy('codigo')->get(),
        ];
    }

    public function create()
    {
        $this->professorId();
        return view('professor.materiais.form', $this->formData(new ObjetoAprendizagem()));
    }

    public function edit(ObjetoAprendizagem $material)
    {
        $this->authorizeMaterial($material);
        return view('professor.materiais.form', $this->formData($material));
    }

    private function validateMaterial(Request $request, ?ObjetoAprendizagem $material = null): array
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['required', 'string', 'max:10000'],
            'tematica' => ['required', 'string', 'max:255'],
            'disciplina_id' => ['required', 'integer', 'exists:disciplinas,id'],
            'ano_escolar' => ['required', Rule::in(['1', '2', '3'])],
            'tipo_objeto' => ['required', Rule::in([
                'video', 'slide', 'documento_pdf', 'podcast_audio',
                'quiz_avaliacao', 'mapa_mental', 'outro',
            ])],
            'status' => ['required', Rule::in(['rascunho', 'publicado'])],
            'tags' => ['nullable', 'string', 'max:255'],
            'link' => ['nullable', 'url:http,https', 'max:255'],
            'imagem' => ['nullable', 'image', 'max:2048'],
            'arquivo' => ['nullable', 'file', 'mimes:pdf,ppt,pptx,doc,docx,mp3,mp4,webm', 'max:10240'],
            'habilidades' => ['nullable', 'array'],
            'habilidades.*' => ['integer', 'exists:habilidades_bncc,id'],
        ]);

        if (! $request->filled('link') && ! $request->hasFile('arquivo')
            && (! $material || ! $material->arquivo)) {
            throw ValidationException::withMessages(['link' => 'Informe um link ou envie um arquivo.']);
        }

        if (! empty($data['habilidades'])) {
            $validCount = HabilidadeBncc::where('disciplina_id', $data['disciplina_id'])
                ->whereIn('id', $data['habilidades'])->count();
            if ($validCount !== count(array_unique($data['habilidades']))) {
                throw ValidationException::withMessages(['habilidades' => 'Selecione habilidades da disciplina escolhida.']);
            }
        }

        return $data;
    }

    private function persist(Request $request, ObjetoAprendizagem $material, array $data): void
    {
        $disciplina = Disciplina::findOrFail($data['disciplina_id']);
        $oldFiles = [$material->imagem, $material->arquivo];
        $attrs = [
            'curso' => 'Ensino Médio',
            'tematica' => $data['tematica'],
            'nome_disciplina' => $disciplina->nome,
            'titulo' => $data['titulo'],
            'descricao' => $data['descricao'],
            'tipo_objeto' => $data['tipo_objeto'],
            'disciplina_id' => $disciplina->id,
            'ano_escolar' => $data['ano_escolar'],
            'status' => $data['status'],
            'tags' => $data['tags'] ?? null,
            'link' => $data['link'] ?? '',
            'bncc' => ! empty($data['habilidades'])
                ? HabilidadeBncc::whereIn('id', $data['habilidades'])->orderBy('id')->value('codigo')
                : null,
        ];
        if (! $material->exists) {
            $attrs['professor_id'] = $this->professorId();
        }

        if ($request->hasFile('imagem')) {
            $attrs['imagem'] = $request->file('imagem')->store('pcct/imagens', 'local');
        }
        if ($request->hasFile('arquivo')) {
            $attrs['arquivo'] = $request->file('arquivo')->store('pcct/arquivos', 'local');
        }

        try {
            DB::transaction(function () use ($material, $attrs, $data) {
                $material->fill($attrs)->save();
                $material->habilidades()->sync($data['habilidades'] ?? []);
            });
        } catch (\Throwable $e) {
            foreach (['imagem', 'arquivo'] as $field) {
                if (isset($attrs[$field])) {
                    Storage::disk('local')->delete($attrs[$field]);
                }
            }
            throw $e;
        }

        foreach (['imagem', 'arquivo'] as $index => $field) {
            if (isset($attrs[$field]) && $oldFiles[$index]) {
                Storage::disk('local')->delete($oldFiles[$index]);
            }
        }
    }

    public function store(Request $request)
    {
        $this->professorId();
        $data = $this->validateMaterial($request);
        $this->persist($request, new ObjetoAprendizagem(), $data);
        return redirect()->route('professor.materiais.index')->with('success', 'Material salvo.');
    }

    public function update(Request $request, ObjetoAprendizagem $material)
    {
        $this->authorizeMaterial($material);
        $data = $this->validateMaterial($request, $material);
        $this->persist($request, $material, $data);
        return redirect()->route('professor.materiais.index')->with('success', 'Material atualizado.');
    }

    public function destroy(ObjetoAprendizagem $material)
    {
        $this->authorizeMaterial($material);
        $files = [$material->imagem, $material->arquivo];
        $material->delete();
        foreach ($files as $file) {
            if ($file) {
                Storage::disk('local')->delete($file);
            }
        }
        return back()->with('success', 'Material excluído.');
    }
}
