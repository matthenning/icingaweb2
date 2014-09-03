<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Web;

use Icinga\Exception\ConfigurationError;
use Zend_Config;
use RecursiveIterator;
use Icinga\Application\Config;
use Icinga\Application\Icinga;
use Icinga\Exception\ProgrammingError;

class Menu implements RecursiveIterator
{
    /**
     * The id of this menu
     *
     * @type string
     */
    protected $id;

    /**
     * The title of this menu
     *
     * Used for sorting when priority is unset or equal to other items
     *
     * @type string
     */
    protected $title;

    /**
     * The priority of this menu
     *
     * Used for sorting
     *
     * @type int
     */
    protected $priority = 100;

    /**
     * The url of this menu
     *
     * @type string
     */
    protected $url;

    /**
     * The path to the icon of this menu
     *
     * @type string
     */
    protected $icon;

    /**
     * The sub menus of this menu
     *
     * @type array
     */
    protected $subMenus = array();

    /**
     * Create a new menu
     *
     * @param   int             $id         The id of this menu
     * @param   Zend_Config     $config     The configuration for this menu
     */
    public function __construct($id, Zend_Config $config = null)
    {
        $this->id = $id;
        $this->setProperties($config);
    }

    /**
     * Set all given properties
     *
     * @param   array|Zend_Config   $props Property list
     */
    public function setProperties($props = null)
    {
        if ($props !== null) {
            foreach ($props as $key => $value) {
                $method = 'set' . implode('', array_map('ucfirst', explode('_', strtolower($key))));
                if (method_exists($this, $method)) {
                    $this->{$method}($value);
                } else {
                    throw new ConfigurationError(
                        sprintf('Menu got invalid property "%s"', $key)
                    );
                }
            }
        }
        return $this;
    }

    /**
     * Get Properties
     *
     * @return array
     */
    public function getProperties()
    {
        $props = array();
        $keys = array('url', 'icon', 'priority', 'title');
        foreach ($keys as $key) {
            $func = 'get' . ucfirst($key);
            if (null !== ($val = $this->{$func}())) {
                $props[$key] = $val;
            }
        }
        return $props;
    }

    /**
     * Whether this Menu conflicts with the given Menu object
     *
     * @param Menu $menu
     * @return bool
     */
    public function conflictsWith(Menu $menu)
    {
        if ($menu->getUrl() === null || $this->getUrl() === null) {
            return false;
        }
        return $menu->getUrl() !== $this->getUrl();
    }

    /**
     * Create menu from the application's menu config file plus the config files from all enabled modules
     *
     * THIS IS OBSOLATE. LEFT HERE FOR FUTURE USE WITH USER-SPECIFIC MODULES
     *
     * @return  self
     */
    public static function fromConfig()
    {
        $menu = new static('menu');
        $manager = Icinga::app()->getModuleManager();
        $modules = $manager->listEnabledModules();
        $menuConfigs = array(Config::app('menu'));

        foreach ($modules as $moduleName) {
            $moduleMenuConfig = Config::module($moduleName, 'menu');
            if (false === empty($moduleMenuConfig)) {
                $menuConfigs[] = $moduleMenuConfig;
            }
        }

        return $menu->loadSubMenus($menu->flattenConfigs($menuConfigs));
    }

    /**
     * Create menu from the application's menu config plus menu entries provided by all enabled modules
     *
     * @return  self
     */
    public static function load()
    {
        /** @var $menu \Icinga\Web\Menu */
        $menu = new static('menu');
        $menu->addMainMenuItems();
        $manager = Icinga::app()->getModuleManager();
        foreach ($manager->getLoadedModules() as $module) {
            /** @var $module \Icinga\Application\Modules\Module */
            $menu->mergeSubMenus($module->getMenuItems());
        }
        return $menu->order();
    }

    /**
     * Add Applications Main Menu Items
     */
    protected function addMainMenuItems()
    {
        $this->add(t('Dashboard'), array(
            'url'      => 'dashboard',
            'icon'     => 'img/icons/dashboard.png',
            'priority' => 10
        ));

        $section = $this->add(t('System'), array(
            'icon'     => 'img/icons/configuration.png',
            'priority' => 200
        ));
        $section->add(t('Preferences'), array(
            'url'      => 'preference',
            'priority' => 200
        ));
        $section->add(t('Configuration'), array(
            'url'      => 'config',
            'priority' => 300
        ));
        $section->add(t('Modules'), array(
            'url'      => 'config/modules',
            'priority' => 400
        ));
        $section->add(t('ApplicationLog'), array(
            'url'      => 'list/applicationlog',
            'priority' => 500
        ));

        $this->add(t('Logout'), array(
            'url'      => 'authentication/logout',
            'icon'     => 'img/icons/logout.png',
            'priority' => 300
        ));
    }

    /**
     * Set the id of this menu
     *
     * @param   string  $id     The id to set for this menu
     *
     * @return  self
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Return the id of this menu
     *
     * @return  string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the title of this menu
     *
     * @param   string  $title  The title to set for this menu
     *
     * @return  self
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Return the title of this menu if set, otherwise its id
     *
     * @return  string
     */
    public function getTitle()
    {
        return $this->title ? $this->title : $this->id;
    }

    /**
     * Set the priority of this menu
     *
     * @param   int     $priority   The priority to set for this menu
     *
     * @return  self
     */
    public function setPriority($priority)
    {
        $this->priority = (int) $priority;
        return $this;
    }

    /**
     * Return the priority of this menu
     *
     * @return  int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * Set the url of this menu
     *
     * @param   string  $url    The url to set for this menu
     *
     * @return  self
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Return the url of this menu
     *
     * @return  string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set the path to the icon of this menu
     *
     * @param   string  $path   The path to the icon for this menu
     *
     * @return  self
     */
    public function setIcon($path)
    {
        $this->icon = $path;
        return $this;
    }

    /**
     * Return the path to the icon of this menu
     *
     * @return  string
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * Return whether this menu has any sub menus
     *
     * @return  bool
     */
    public function hasSubMenus()
    {
        return false === empty($this->subMenus);
    }

    /**
     * Add a sub menu to this menu
     *
     * @param   string          $id             The id of the menu to add
     * @param   Zend_Config     $itemConfig     The config with which to initialize the menu
     *
     * @return  self
     */
    public function addSubMenu($id, Zend_Config $menuConfig = null)
    {
        if (false === ($pos = strpos($id, '.'))) {
            $subMenu = new self($id, $menuConfig);
            $this->subMenus[$id] = $subMenu;
        } else {
            list($parentId, $id) = explode('.', $id, 2);

            if ($this->hasSubMenu($parentId)) {
                $parent = $this->getSubMenu($parentId);
            } else {
                $parent = $this->addSubMenu($parentId);
            }

            $subMenu = $parent->addSubMenu($id, $menuConfig);
        }

        return $subMenu;
    }

    /**
     * Set required Permissions
     *
     * @param $permission
     * @return $this
     */
    public function requirePermission($permission)
    {
        // Not implemented yet
        return $this;
    }

    /**
     * Merge Sub Menus
     *
     * @param array $submenus
     * @return $this
     */
    public function mergeSubMenus(array $submenus)
    {
        foreach ($submenus as $menu) {
            $this->mergeSubMenu($menu);
        }
        return $this;
    }

    /**
     * Merge Sub Menu
     *
     * @param Menu $menu
     * @return mixed
     */
    public function mergeSubMenu(Menu $menu)
    {
        $name = $menu->getId();
        if (array_key_exists($name, $this->subMenus)) {
            /** @var $current Menu */
            $current = $this->subMenus[$name];
            if ($current->conflictsWith($menu)) {
                while (array_key_exists($name, $this->subMenus)) {
                    if (preg_match('/_(\d+)$/', $name, $m)) {
                        $name = preg_replace('/_\d+$/', $m[1]++, $name);
                    } else {
                        $name .= '_2';
                    }
                }
                $menu->setId($name);
                $this->subMenus[$name] = $menu;
            } else {
                $current->setProperties($menu->getProperties());
                foreach ($menu->subMenus as $child) {
                    $current->mergeSubMenu($child);
                }
            }
        } else {
            $this->subMenus[$name] = $menu;
        }

        return $this->subMenus[$name];
    }

    /**
     * Add a Menu
     *
     * @param $name
     * @param array $config
     * @return Menu
     */
    public function add($name, $config = array())
    {
        return $this->addSubMenu($name, new Zend_Config($config));
    }

    /**
     * Return whether a sub menu with the given id exists
     *
     * @param   string  $id     The id of the sub menu
     *
     * @return  bool
     */
    public function hasSubMenu($id)
    {
        return array_key_exists($id, $this->subMenus);
    }

    /**
     * Get sub menu by its id
     *
     * @param   string      $id     The id of the sub menu
     *
     * @return  Menu                The found sub menu
     *
     * @throws  ProgrammingError    In case there is no sub menu with the given id to be found
     */
    public function getSubMenu($id)
    {
        if (false === $this->hasSubMenu($id)) {
            throw new ProgrammingError(
                'Tried to get invalid sub menu "%s"',
                $id
            );
        }

        return $this->subMenus[$id];
    }

    /**
     * Order this menu's sub menus based on their priority
     *
     * @return  self
     */
    public function order()
    {
        uasort($this->subMenus, array($this, 'cmpSubMenus'));
        foreach ($this->subMenus as $subMenu) {
            if ($subMenu->hasSubMenus()) {
                $subMenu->order();
            }
        }

        return $this;
    }

    /**
     * Compare sub menus based on priority and title
     *
     * @param   Menu    $a
     * @param   Menu    $b
     *
     * @return  int
     */
    protected function cmpSubMenus($a, $b)
    {
        if ($a->priority == $b->priority) {
            return $a->getTitle() > $b->getTitle() ? 1 : (
                $a->getTitle() < $b->getTitle() ? -1 : 0
            );
        }

        return $a->priority > $b->priority ? 1 : -1;
    }

    /**
     * Flatten configs
     *
     * @param   array   $configs    An two dimensional array of menu configurations
     *
     * @return  array               The flattened config, as key-value array
     */
    protected function flattenConfigs(array $configs)
    {
        $flattened = array();
        foreach ($configs as $menuConfig) {
            foreach ($menuConfig as $section => $itemConfig) {
                while (array_key_exists($section, $flattened)) {
                    $section .= '_dup';
                }
                $flattened[$section] = $itemConfig;
            }
        }

        return $flattened;
    }

    /**
     * Load the sub menus
     *
     * @param   array   $menus  The menus to load, as key-value array
     *
     * @return  self
     */
    protected function loadSubMenus(array $menus)
    {
        foreach ($menus as $menuId => $menuConfig) {
            $this->addSubMenu($menuId, $menuConfig);
        }

        return $this;
    }

    /**
     * Check whether the current menu node has any sub menus
     *
     * @return  bool
     */
    public function hasChildren()
    {
        $current = $this->current();
        if (false !== $current) {
            return $current->hasSubMenus();
        }

        return false;
    }

    /**
     * Return a iterator for the current menu node
     *
     * @return  RecursiveIterator
     */
    public function getChildren()
    {
        return $this->current();
    }

    /**
     * Rewind the iterator to its first menu node
     */
    public function rewind()
    {
        reset($this->subMenus);
    }

    /**
     * Return whether the iterator position is valid
     *
     * @return bool
     */
    public function valid()
    {
        return $this->key() !== null;
    }

    /**
     * Return the current menu node
     *
     * @return Menu
     */
    public function current()
    {
        return current($this->subMenus);
    }

    /**
     * Return the id of the current menu node
     *
     * @return string
     */
    public function key()
    {
        return key($this->subMenus);
    }

    /**
     * Move the iterator to the next menu node
     */
    public function next()
    {
        next($this->subMenus);
    }
}
