<?php

namespace App\Extensions;

use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Database\ConnectionInterface;

class ConsoDbSessionHandler extends DatabaseSessionHandler
{
    protected $request;

    public function __construct(ConnectionInterface $connection, $table, $minutes, Request $request)
    {
        parent::__construct($connection, $table, $minutes);
        $this->request = $request;
    }

    /**
     * Write the session data to the storage.
     *
     * @param  string  $sessionId
     * @param  array  $payload
     * @return bool
     */
    public function write($sessionId, $payload): bool
    {
        $payload = $this->getDefaultPayload($payload);
        if (Auth::check()) {
            $session = $this->request->session();
            $payload['user_id'] = $this->request->session()->get('user_id');
            $payload['conso_id'] = $this->request->session()->get('conso_id');
            if ($this->exists) {
                $this->getQuery()->where('id', $sessionId)->update($payload);
            } else {
                $payload['id'] = $sessionId;
                $this->getQuery()->insert($payload);
            }
        }
        return true;
    }
}
