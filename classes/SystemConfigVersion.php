<?php namespace ProcessWire;

/**
 * @property  int $status
 * @property-read int $version_no
 * @property-read int $run_date
 * @property-read int $filename
 */
class SystemConfigVersion extends WireData {
    public const STATUS_UNDEFINED = 0;
    public const STATUS_DONE = 1;
    public const STATUS_NEW = 2;

    public function __construct() {
        parent::__construct();
        $this->set('status', self::STATUS_UNDEFINED);
    }

    public function setFromDatabase(array $data): self {
        $this->setArray($data);
        $this->set('status', self::STATUS_DONE);

        return $this;
    }

    public function setFromFile(int $versionNumber, string $fileName): self {
        $this->set('version_no', $versionNumber);
        $this->set('filename', $fileName);
        $this->set('status', self::STATUS_NEW);

        return $this;
    }
}
