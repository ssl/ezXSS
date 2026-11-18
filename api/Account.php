<?php
class AccountController extends Controller
{
    /**
     * Routable HTTP actions (everything else is an internal helper).
     */
    public static $routable = array('notepad');

    /**
     * Maximum stored notepad length (characters).
     */
    const NOTEPAD_MAX = 50000;

    /**
     * GET reads, POST saves the caller's notepad.
     */
    public function notepad()
    {
        $this->begin(array('GET', 'POST'));

        if (isPOST()) {
            return $this->respond($this->doSaveNotepad($this->input()));
        }

        $this->respond($this->doGetNotepad());
    }

    /**
     * Read the acting user's own notepad text.
     *
     * @return array
     */
    public function doGetNotepad()
    {
        $actor = Auth::user();

        return array('notepad' => $actor['notepad']);
    }

    /**
     * Save the acting user's own notepad text.
     *
     * @param string $body
     * @return array
     */
    public function doSaveNotepad($body)
    {
        $actor = Auth::user();
        $notepad = $this->validateNotepad($body);

        User::update((int) $actor['id'], array('notepad' => $notepad));
        return array('notepad' => $notepad);
    }

    /**
     * Validate and coerce a notepad body to a bounded string.
     *
     * @param string $body
     * @throws HttpException
     */
    public function validateNotepad($body)
    {
        if (!array_key_exists('notepad', $body) || !is_string($body['notepad'])) {
            throw new HttpException(422, 'notepad must be a string');
        }
        $notepad = $body['notepad'];
        if (strlen($notepad) > self::NOTEPAD_MAX) {
            throw new HttpException(422, 'notepad is too long (max ' . self::NOTEPAD_MAX . ' characters)');
        }
        return $notepad;
    }
}
