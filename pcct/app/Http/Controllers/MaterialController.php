namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfessorController extends Controller
{
    public function create()
    {
        return view('professor.cadastro');
    }

    public function store(Request $request)
    {
        dd($request->all());
    }


}