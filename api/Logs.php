<?php
class LogsController extends Controller
{
    /**
     * Routable HTTP actions (everything else is an internal helper).
     */
    public static $routable = array('index');

    /**
     * GET /api/logs - paginated audit list (log.view).
     */
    public function index()
    {
        $this->begin('GET');

        $result = $this->doList();
        $this->respond($result['data'], array('pagination' => $result['pagination']));
    }

    /**
     * Build the paginated log list. Requires log.view (global data).
     *
     * @return array
     */
    public function doList()
    {
        $this->cap('log.view');

        $perPage = $this->intParam('per_page', 25, 1, 200);
        $page = $this->intParam('page', 1, 1, PHP_INT_MAX);
        $offset = ($page - 1) * $perPage;
        $filters = $this->filters();

        $data = array();
        foreach (Log::listPaged($perPage, $offset, $filters) as $row) {
            $data[] = array(
                'id'          => (int) $row['id'],
                'user_id'     => (int) $row['user_id'],
                'type'        => (string) (isset($row['type']) ? $row['type'] : ''),
                'description' => (string) $row['description'],
                'ip'          => (string) $row['ip'],
                'created_at'  => (int) $row['created_at'],
            );
        }

        $total = Log::countFiltered($filters);

        return array(
            'data'       => $data,
            'pagination' => $this->pagination($total, $page, $perPage),
        );
    }

    /**
     * Build the filter map from the query string (all optional, all validated).
     */
    public function filters()
    {
        $f = array();

        $user = queryParam('user', null);
        if ($user !== null && ctype_digit((string) $user)) {
            $f['user_id'] = (int) $user;
        }

        $type = queryParam('type', null);
        if (is_string($type) && $type !== '' && in_array($type, Log::CATEGORIES, true)) {
            $f['type'] = $type;
        }

        $from = queryParam('from', null);
        if ($from !== null && ctype_digit((string) $from)) {
            $f['from'] = (int) $from;
        }
        $to = queryParam('to', null);
        if ($to !== null && ctype_digit((string) $to)) {
            $f['to'] = (int) $to;
        }

        $search = queryParam('search', null);
        if (is_string($search) && trim($search) !== '') {
            $f['search'] = trim($search);
        }

        return $f;
    }
}
