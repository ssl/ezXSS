<?php

class Settings extends Controller
{

    public function index()
    {
        $this->isAdminOrExit();

        $this->view->setTitle('Settings');
        $this->view->renderTemplate('settings/index');

        if ($this->isPOST()) {
            $this->validateCsrfToken();

            $timezone = $this->getPostValue('timezone');
            $theme = $this->getPostValue('theme');
            $filter = $this->getPostValue('filter');
            $dompart = $this->getPostValue('dompart');

            if (!in_array($timezone, timezone_identifiers_list(), true)) {
                throw new Exception('The timezone is not a valid timezone.');
            }

            $theme = preg_replace('/[^a-zA-Z0-9]/', '', $theme);
            if(!file_exists(__DIR__ . "/../../assets/css/{$theme}.css")) {
                throw new Exception('This theme is not installed.');
            }

            if (!ctype_digit($dompart)) {
                throw new Exception('The dom length needs to be a int number.');
            }

            $filterSave = ($filter == 1 || $filter == 2) ? 1 : 0;
            $filterAlert = ($filter == 1 || $filter == 3) ? 1 : 0;

            $this->model('Setting')->set('dompart', $dompart);
            $this->model('Setting')->set('filter-save', $filterSave);
            $this->model('Setting')->set('filter-alert', $filterAlert);
            $this->model('Setting')->set('timezone', $timezone);
            $this->model('Setting')->set('theme', $theme);
        }

        $timezones = [];
        $timezone = $this->model('Setting')->get('timezone');
        foreach (timezone_identifiers_list() as $key => $name) {
            $selected = $timezone == $name ? 'selected' : '';
            $timezones[$key]['html'] = "<option $selected value=\"$name\">$name</option>";
        }
        $this->view->renderDataset('timezone', $timezones, true);

        $themes = [];
        $theme = $this->model('Setting')->get('theme');
        $files = array_diff(scandir(__DIR__ . '/../../assets/css'), array('.', '..'));
        foreach ($files as $file) {
            $themeName = e(str_replace('.css', '', $file));
            $selected = $theme == $themeName ? 'selected' : '';
            $themes[$themeName]['html'] = "<option $selected value=\"$themeName\">$themeName</option>";
        }
        $this->view->renderDataset('theme', $themes, true);

        $filterSave = $this->model('Setting')->get('filter-save');
        $filterAlert = $this->model('Setting')->get('filter-alert');

        $this->view->renderData('filter1', ($filterSave == 1 && $filterAlert) == 1 ? 'selected' : '');
        $this->view->renderData('filter2', ($filterSave == 1 && $filterAlert) == 0 ? 'selected' : '');
        $this->view->renderData('filter3', ($filterSave == 0 && $filterAlert) == 1 ? 'selected' : '');
        $this->view->renderData('filter4', ($filterSave == 0 && $filterAlert) == 0 ? 'selected' : '');

        $this->view->renderData('dompart', $this->model('Setting')->get('dompart'));

        return $this->view->showContent();
    }
}
