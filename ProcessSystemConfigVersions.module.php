<?php

namespace ProcessWire;

use PDO;

class ProcessSystemConfigVersions extends Process {
    private const TABLE_NAME = 'system_config_versions';

    private const VERSIONS_DIR = 'versions/';
    const FILE_EXTENSION = 'version.php';

    public static function getModuleInfo(): array {
        return [
            'title' => 'SystemConfigVersions',
            'version' => 100,
            'summary' => 'Configure your ProcessWire installation with migration files',
            'autoload' => false,
            'singular' => true,
            'requires' => [
                'PHP>=8.1',
            ],
            'icon' => 'line-chart',
            'page' => [
                'name' => 'versions',
                'parent' => 'setup',
                'title' => 'System Config Versions',
                'icon' => 'line-chart',
            ],
        ];
    }

    public function ___install(): void {
        parent::___install();

        $this->database->query("
			CREATE TABLE " . self::TABLE_NAME . " (
				id int unsigned NOT NULL AUTO_INCREMENT,
				version_no int unsigned DEFAULT 0,
				run_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				filename varchar(250) NOT NULL DEFAULT '',
				PRIMARY KEY(id),
				UNIQUE KEY(version_no)
			) ENGINE = InnoDB;
		");
    }

    public function ___uninstall(): void {
        parent::___uninstall();

        $this->database->query("DROP TABLE IF EXISTS " . self::TABLE_NAME);
    }

    public function ready() {}

    public function init(): void {
        require_once __DIR__ . '/classes/SystemConfigVersion.php';
    }

    public function ___execute(): string {
        $newVersionsAvailable = false;

        /** @var MarkupAdminDataTable $table */
        $table = $this->modules->get("MarkupAdminDataTable");
        $table->setEncodeEntities(false);

        $table->headerRow([
            $this->_('Version No.'),
            $this->_('File Name'),
            $this->_('Run Date'),
            $this->_('Status'),
        ]);

        foreach ($this->getAvailableVersions() as $version) {
            $statusMarkup = '-';

            if ($version->status === SystemConfigVersion::STATUS_NEW) {
                $newVersionsAvailable = true;
                $statusMarkup = '<a href="./run/?id=' . $version->version_no . '"><i class="fa fa-forward fa-fw"></i></a>';
            }

            $table->row([
                $version->version_no,
                $version->filename,
                $version->run_date ?? '-',
                $statusMarkup,
            ]);
        }

        $controlsMarkup = '';

        if ($newVersionsAvailable) {
            /** @var InputfieldButton $button */
            $button = $this->modules->get('InputfieldButton');
            $button->attr('href', './runAll');
            $button->attr('id', 'run_all');
            $button->attr('value', 'Run All');
            $button->icon = 'fast-forward';

            $controlsMarkup .= $button->render();
        }

        /** @var InputfieldButton $button */
        $button = $this->modules->get('InputfieldButton');
        $button->attr('href', './');
        $button->attr('id', 'refresh');
        $button->showInHeader();
        $button->attr('value', 'Refresh');
        $button->setSecondary();
        $button->icon = 'refresh';

        $controlsMarkup .= $button->render();

        return '<div class="system-config-versions-list">' . $table->render() . '</div>' . $controlsMarkup;
    }

    public function ___executeRun(): void {
        $id = $this->input->get('id');

        $versions = $this->getAvailableVersions();

        if (array_key_exists($id, $versions)) {
            $version = $versions[$id];

            $runResult = $this->files->render($this->config->paths->templates . self::VERSIONS_DIR . $version->filename . '.' . self::FILE_EXTENSION, [], [
                'throwExceptions' => false,
            ]);

            if ($runResult !== false) {
                $statement = $this->database->prepare("INSERT INTO " . self::TABLE_NAME . " (version_no, filename) VALUES (:vn, :fn)");
                $statement->bindValue('vn', $version->version_no, PDO::PARAM_INT);
                $statement->bindValue('fn', $version->filename);
                $statement->execute();
            }
        }

        $this->session->redirect('../', false);
    }

    /**
     * @return SystemConfigVersion[]
     */
    public function getAvailableVersions(): array {
        $versions = [];

        $databaseVersions = $this->database->query("SELECT * FROM " . self::TABLE_NAME . " ORDER BY version_no");
        foreach ($databaseVersions as $databaseVersion) {
            /** @var SystemConfigVersion $newVersion */
            $newVersion = $this->wire(new SystemConfigVersion());
            $versions[$databaseVersion['version_no']] = $newVersion->setFromDatabase($databaseVersion);
        }

        $filesystemVersions = $this->files->find($this->config->paths->templates . self::VERSIONS_DIR, [
            'recursive' => false,
            'extensions' => self::FILE_EXTENSION,
            'returnRelative' => true,
        ]);

        foreach ($filesystemVersions as $filesystemVersion) {
            if (preg_match('/^((\d{4,5})-[^.]+)\.version\.php$/', $filesystemVersion, $matches)) {
                if (!array_key_exists($matches[2], $versions)) {
                    /** @var SystemConfigVersion $newVersion */
                    $newVersion = $this->wire(new SystemConfigVersion());
                    $versions[$matches[2]] = $newVersion->setFromFile($matches[2], $matches[1]);
                }
            }
        }

        return $versions;
    }
}
