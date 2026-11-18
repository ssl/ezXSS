<?php
class PingController extends Controller
{
    /**
     * Web-reachable actions.
     */
    public static $routable = array('index');

    /**
     * Return a simple pong envelope.
     */
    public function index()
    {
        $this->respond('pong');
    }
}
