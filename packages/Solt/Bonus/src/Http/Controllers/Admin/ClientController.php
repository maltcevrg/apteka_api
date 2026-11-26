<?php

namespace Solt\Bonus\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Solt\Bonus\Models\AdminLog;

/**
 * Кассы — клиенты API (Passport client_credentials): создать, перевыпустить секрет, отозвать.
 * Только администратор.
 */
class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::query()->orderBy('revoked')->orderBy('created_at')->get()
            ->filter(fn (Client $c) => $c->hasGrantType('client_credentials'))
            ->values();

        return view('bonus::admin.clients', ['clients' => $clients]);
    }

    public function store(Request $request, ClientRepository $repo)
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:100']]);
        $client = $repo->createClientCredentialsGrantClient($data['name']);

        AdminLog::write('client.create', $data['name'], ['client_id' => (string) $client->getKey()]);
        return redirect()->route('admin.clients')->with('new_client', [
            'id' => (string) $client->getKey(), 'secret' => $client->plainSecret, 'name' => $client->name,
        ]);
    }

    public function regenerate(Client $client, ClientRepository $repo)
    {
        $repo->regenerateSecret($client);
        AdminLog::write('client.secret', $client->name, ['client_id' => (string) $client->getKey()]);
        return redirect()->route('admin.clients')->with('new_client', [
            'id' => (string) $client->getKey(), 'secret' => $client->plainSecret, 'name' => $client->name,
        ]);
    }

    public function revoke(Client $client, ClientRepository $repo)
    {
        $active = Client::where('revoked', false)->get()->filter(fn (Client $c) => $c->hasGrantType('client_credentials'))->count();
        if ($active <= 1 && !$client->revoked) {
            return back()->withErrors(['Нельзя отозвать последнюю действующую кассу — сначала создайте новую']);
        }
        $repo->delete($client);
        AdminLog::write('client.revoke', $client->name, ['client_id' => (string) $client->getKey()]);
        return back()->with('success', "Касса «{$client->name}» отозвана: её токены больше не действуют");
    }
}
