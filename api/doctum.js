var Doctum = {
    treeJson: {"tree":{"l":0,"n":"","p":"","c":[{"l":1,"n":"Drush","p":"Drush","c":[{"l":2,"n":"Attributes","p":"Drush/Attributes","c":[{"l":3,"n":"Argument","p":"Drush/Attributes/Argument"},{"l":3,"n":"Bootstrap","p":"Drush/Attributes/Bootstrap"},{"l":3,"n":"Command","p":"Drush/Attributes/Command"},{"l":3,"n":"Complete","p":"Drush/Attributes/Complete"},{"l":3,"n":"DefaultFields","p":"Drush/Attributes/DefaultFields"},{"l":3,"n":"DefaultTableFields","p":"Drush/Attributes/DefaultTableFields"},{"l":3,"n":"FieldLabels","p":"Drush/Attributes/FieldLabels"},{"l":3,"n":"FilterDefaultField","p":"Drush/Attributes/FilterDefaultField"},{"l":3,"n":"Help","p":"Drush/Attributes/Help"},{"l":3,"n":"Hook","p":"Drush/Attributes/Hook"},{"l":3,"n":"Kernel","p":"Drush/Attributes/Kernel"},{"l":3,"n":"Misc","p":"Drush/Attributes/Misc"},{"l":3,"n":"NoArgumentsBase","p":"Drush/Attributes/NoArgumentsBase"},{"l":3,"n":"Option","p":"Drush/Attributes/Option"},{"l":3,"n":"OptionsetGetEditor","p":"Drush/Attributes/OptionsetGetEditor"},{"l":3,"n":"OptionsetProcBuild","p":"Drush/Attributes/OptionsetProcBuild"},{"l":3,"n":"OptionsetSql","p":"Drush/Attributes/OptionsetSql"},{"l":3,"n":"OptionsetSsh","p":"Drush/Attributes/OptionsetSsh"},{"l":3,"n":"OptionsetTableSelection","p":"Drush/Attributes/OptionsetTableSelection"},{"l":3,"n":"Topics","p":"Drush/Attributes/Topics"},{"l":3,"n":"Usage","p":"Drush/Attributes/Usage"},{"l":3,"n":"ValidateEntityLoad","p":"Drush/Attributes/ValidateEntityLoad"},{"l":3,"n":"ValidateFileExists","p":"Drush/Attributes/ValidateFileExists"},{"l":3,"n":"ValidateModulesEnabled","p":"Drush/Attributes/ValidateModulesEnabled"},{"l":3,"n":"ValidatePermissions","p":"Drush/Attributes/ValidatePermissions"},{"l":3,"n":"ValidatePhpExtensions","p":"Drush/Attributes/ValidatePhpExtensions"},{"l":3,"n":"Version","p":"Drush/Attributes/Version"}]},{"l":2,"n":"Backend","p":"Drush/Backend","c":[{"l":3,"n":"BackendPathEvaluator","p":"Drush/Backend/BackendPathEvaluator"}]},{"l":2,"n":"Boot","p":"Drush/Boot","c":[{"l":3,"n":"AutoloaderAwareInterface","p":"Drush/Boot/AutoloaderAwareInterface"},{"l":3,"n":"AutoloaderAwareTrait","p":"Drush/Boot/AutoloaderAwareTrait"},{"l":3,"n":"BaseBoot","p":"Drush/Boot/BaseBoot"},{"l":3,"n":"Boot","p":"Drush/Boot/Boot"},{"l":3,"n":"BootstrapHook","p":"Drush/Boot/BootstrapHook"},{"l":3,"n":"BootstrapManager","p":"Drush/Boot/BootstrapManager"},{"l":3,"n":"DrupalBoot","p":"Drush/Boot/DrupalBoot"},{"l":3,"n":"DrupalBoot8","p":"Drush/Boot/DrupalBoot8"},{"l":3,"n":"DrupalBootLevels","p":"Drush/Boot/DrupalBootLevels"},{"l":3,"n":"EmptyBoot","p":"Drush/Boot/EmptyBoot"},{"l":3,"n":"Kernels","p":"Drush/Boot/Kernels"}]},{"l":2,"n":"Command","p":"Drush/Command","c":[{"l":3,"n":"DrushCommandInfoAlterer","p":"Drush/Command/DrushCommandInfoAlterer"},{"l":3,"n":"GlobalOptionsEventListener","p":"Drush/Command/GlobalOptionsEventListener"},{"l":3,"n":"RemoteCommandProxy","p":"Drush/Command/RemoteCommandProxy"},{"l":3,"n":"ServiceCommandlist","p":"Drush/Command/ServiceCommandlist"}]},{"l":2,"n":"Commands","p":"Drush/Commands","c":[{"l":3,"n":"config","p":"Drush/Commands/config","c":[{"l":4,"n":"ConfigPullCommands","p":"Drush/Commands/config/ConfigPullCommands"}]},{"l":3,"n":"core","p":"Drush/Commands/core","c":[{"l":4,"n":"ArchiveDumpCommands","p":"Drush/Commands/core/ArchiveDumpCommands"},{"l":4,"n":"ArchiveRestoreCommands","p":"Drush/Commands/core/ArchiveRestoreCommands"},{"l":4,"n":"BrowseCommands","p":"Drush/Commands/core/BrowseCommands"},{"l":4,"n":"CacheCommands","p":"Drush/Commands/core/CacheCommands"},{"l":4,"n":"CoreCommands","p":"Drush/Commands/core/CoreCommands"},{"l":4,"n":"DeployCommands","p":"Drush/Commands/core/DeployCommands"},{"l":4,"n":"DocsCommands","p":"Drush/Commands/core/DocsCommands"},{"l":4,"n":"DrupalDirectoryCommands","p":"Drush/Commands/core/DrupalDirectoryCommands"},{"l":4,"n":"DrupliconCommands","p":"Drush/Commands/core/DrupliconCommands"},{"l":4,"n":"EditCommands","p":"Drush/Commands/core/EditCommands"},{"l":4,"n":"LoginCommands","p":"Drush/Commands/core/LoginCommands"},{"l":4,"n":"MkCommands","p":"Drush/Commands/core/MkCommands"},{"l":4,"n":"NotifyCommands","p":"Drush/Commands/core/NotifyCommands"},{"l":4,"n":"PhpCommands","p":"Drush/Commands/core/PhpCommands"},{"l":4,"n":"RsyncCommands","p":"Drush/Commands/core/RsyncCommands"},{"l":4,"n":"RunserverCommands","p":"Drush/Commands/core/RunserverCommands"},{"l":4,"n":"SiteCommands","p":"Drush/Commands/core/SiteCommands"},{"l":4,"n":"SiteInstallCommands","p":"Drush/Commands/core/SiteInstallCommands"},{"l":4,"n":"SshCommands","p":"Drush/Commands/core/SshCommands"},{"l":4,"n":"StatusCommands","p":"Drush/Commands/core/StatusCommands"},{"l":4,"n":"TopicCommands","p":"Drush/Commands/core/TopicCommands"},{"l":4,"n":"UpdateDBCommands","p":"Drush/Commands/core/UpdateDBCommands"},{"l":4,"n":"XhprofCommands","p":"Drush/Commands/core/XhprofCommands"}]},{"l":3,"n":"generate","p":"Drush/Commands/generate","c":[{"l":4,"n":"Generators","p":"Drush/Commands/generate/Generators","c":[{"l":5,"n":"Drush","p":"Drush/Commands/generate/Generators/Drush","c":[{"l":6,"n":"DrushAliasFile","p":"Drush/Commands/generate/Generators/Drush/DrushAliasFile"},{"l":6,"n":"DrushCommandFile","p":"Drush/Commands/generate/Generators/Drush/DrushCommandFile"}]},{"l":5,"n":"Migrate","p":"Drush/Commands/generate/Generators/Migrate","c":[{"l":6,"n":"MigrationGenerator","p":"Drush/Commands/generate/Generators/Migrate/MigrationGenerator"}]}]},{"l":4,"n":"ApplicationFactory","p":"Drush/Commands/generate/ApplicationFactory"},{"l":4,"n":"GenerateCommands","p":"Drush/Commands/generate/GenerateCommands"},{"l":4,"n":"GeneratorClassResolver","p":"Drush/Commands/generate/GeneratorClassResolver"}]},{"l":3,"n":"help","p":"Drush/Commands/help","c":[{"l":4,"n":"DrushHelpDocument","p":"Drush/Commands/help/DrushHelpDocument"},{"l":4,"n":"HelpCLIFormatter","p":"Drush/Commands/help/HelpCLIFormatter"},{"l":4,"n":"HelpCommands","p":"Drush/Commands/help/HelpCommands"},{"l":4,"n":"ListCommands","p":"Drush/Commands/help/ListCommands"}]},{"l":3,"n":"pm","p":"Drush/Commands/pm","c":[{"l":4,"n":"SecurityUpdateCommands","p":"Drush/Commands/pm/SecurityUpdateCommands"}]},{"l":3,"n":"sql","p":"Drush/Commands/sql","c":[{"l":4,"n":"SqlCommands","p":"Drush/Commands/sql/SqlCommands"},{"l":4,"n":"SqlSyncCommands","p":"Drush/Commands/sql/SqlSyncCommands"}]},{"l":3,"n":"DrushCommands","p":"Drush/Commands/DrushCommands"},{"l":3,"n":"ExampleCommands","p":"Drush/Commands/ExampleCommands"},{"l":3,"n":"LegacyCommands","p":"Drush/Commands/LegacyCommands"},{"l":3,"n":"OptionsCommands","p":"Drush/Commands/OptionsCommands"},{"l":3,"n":"ValidatorsCommands","p":"Drush/Commands/ValidatorsCommands"}]},{"l":2,"n":"Config","p":"Drush/Config","c":[{"l":3,"n":"Loader","p":"Drush/Config/Loader","c":[{"l":4,"n":"YamlConfigLoader","p":"Drush/Config/Loader/YamlConfigLoader"}]},{"l":3,"n":"ConfigAwareTrait","p":"Drush/Config/ConfigAwareTrait"},{"l":3,"n":"ConfigLocator","p":"Drush/Config/ConfigLocator"},{"l":3,"n":"DrushConfig","p":"Drush/Config/DrushConfig"},{"l":3,"n":"Environment","p":"Drush/Config/Environment"}]},{"l":2,"n":"Drupal","p":"Drush/Drupal","c":[{"l":3,"n":"Commands","p":"Drush/Drupal/Commands","c":[{"l":4,"n":"config","p":"Drush/Drupal/Commands/config","c":[{"l":5,"n":"ConfigCommands","p":"Drush/Drupal/Commands/config/ConfigCommands"},{"l":5,"n":"ConfigExportCommands","p":"Drush/Drupal/Commands/config/ConfigExportCommands"},{"l":5,"n":"ConfigImportCommands","p":"Drush/Drupal/Commands/config/ConfigImportCommands"}]},{"l":4,"n":"core","p":"Drush/Drupal/Commands/core","c":[{"l":5,"n":"BatchCommands","p":"Drush/Drupal/Commands/core/BatchCommands"},{"l":5,"n":"CliCommands","p":"Drush/Drupal/Commands/core/CliCommands"},{"l":5,"n":"DeployHookCommands","p":"Drush/Drupal/Commands/core/DeployHookCommands"},{"l":5,"n":"DrupalCommands","p":"Drush/Drupal/Commands/core/DrupalCommands"},{"l":5,"n":"EntityCommands","p":"Drush/Drupal/Commands/core/EntityCommands"},{"l":5,"n":"ImageCommands","p":"Drush/Drupal/Commands/core/ImageCommands"},{"l":5,"n":"JsonapiCommands","p":"Drush/Drupal/Commands/core/JsonapiCommands"},{"l":5,"n":"LanguageCommands","p":"Drush/Drupal/Commands/core/LanguageCommands"},{"l":5,"n":"LinkHooks","p":"Drush/Drupal/Commands/core/LinkHooks"},{"l":5,"n":"LocaleCommands","p":"Drush/Drupal/Commands/core/LocaleCommands"},{"l":5,"n":"MaintCommands","p":"Drush/Drupal/Commands/core/MaintCommands"},{"l":5,"n":"MessengerCommands","p":"Drush/Drupal/Commands/core/MessengerCommands"},{"l":5,"n":"MigrateRunnerCommands","p":"Drush/Drupal/Commands/core/MigrateRunnerCommands"},{"l":5,"n":"QueueCommands","p":"Drush/Drupal/Commands/core/QueueCommands"},{"l":5,"n":"RoleCommands","p":"Drush/Drupal/Commands/core/RoleCommands"},{"l":5,"n":"StateCommands","p":"Drush/Drupal/Commands/core/StateCommands"},{"l":5,"n":"TwigCommands","p":"Drush/Drupal/Commands/core/TwigCommands"},{"l":5,"n":"UserCommands","p":"Drush/Drupal/Commands/core/UserCommands"},{"l":5,"n":"ViewsCommands","p":"Drush/Drupal/Commands/core/ViewsCommands"},{"l":5,"n":"WatchdogCommands","p":"Drush/Drupal/Commands/core/WatchdogCommands"}]},{"l":4,"n":"field","p":"Drush/Drupal/Commands/field","c":[{"l":5,"n":"EntityTypeBundleAskTrait","p":"Drush/Drupal/Commands/field/EntityTypeBundleAskTrait"},{"l":5,"n":"EntityTypeBundleValidationTrait","p":"Drush/Drupal/Commands/field/EntityTypeBundleValidationTrait"},{"l":5,"n":"FieldBaseInfoCommands","p":"Drush/Drupal/Commands/field/FieldBaseInfoCommands"},{"l":5,"n":"FieldBaseOverrideCreateCommands","p":"Drush/Drupal/Commands/field/FieldBaseOverrideCreateCommands"},{"l":5,"n":"FieldCreateCommands","p":"Drush/Drupal/Commands/field/FieldCreateCommands"},{"l":5,"n":"FieldDefinitionRowsOfFieldsTrait","p":"Drush/Drupal/Commands/field/FieldDefinitionRowsOfFieldsTrait"},{"l":5,"n":"FieldDeleteCommands","p":"Drush/Drupal/Commands/field/FieldDeleteCommands"},{"l":5,"n":"FieldEntityReferenceHooks","p":"Drush/Drupal/Commands/field/FieldEntityReferenceHooks"},{"l":5,"n":"FieldInfoCommands","p":"Drush/Drupal/Commands/field/FieldInfoCommands"}]},{"l":4,"n":"pm","p":"Drush/Drupal/Commands/pm","c":[{"l":5,"n":"PmCommands","p":"Drush/Drupal/Commands/pm/PmCommands"},{"l":5,"n":"ThemeCommands","p":"Drush/Drupal/Commands/pm/ThemeCommands"}]},{"l":4,"n":"sql","p":"Drush/Drupal/Commands/sql","c":[{"l":5,"n":"SanitizeCommands","p":"Drush/Drupal/Commands/sql/SanitizeCommands"},{"l":5,"n":"SanitizeCommentsCommands","p":"Drush/Drupal/Commands/sql/SanitizeCommentsCommands"},{"l":5,"n":"SanitizePluginInterface","p":"Drush/Drupal/Commands/sql/SanitizePluginInterface"},{"l":5,"n":"SanitizeSessionsCommands","p":"Drush/Drupal/Commands/sql/SanitizeSessionsCommands"},{"l":5,"n":"SanitizeUserFieldsCommands","p":"Drush/Drupal/Commands/sql/SanitizeUserFieldsCommands"},{"l":5,"n":"SanitizeUserTableCommands","p":"Drush/Drupal/Commands/sql/SanitizeUserTableCommands"}]}]},{"l":3,"n":"Migrate","p":"Drush/Drupal/Migrate","c":[{"l":4,"n":"MigrateEvents","p":"Drush/Drupal/Migrate/MigrateEvents"},{"l":4,"n":"MigrateExecutable","p":"Drush/Drupal/Migrate/MigrateExecutable"},{"l":4,"n":"MigrateIdMapFilter","p":"Drush/Drupal/Migrate/MigrateIdMapFilter"},{"l":4,"n":"MigrateMessage","p":"Drush/Drupal/Migrate/MigrateMessage"},{"l":4,"n":"MigrateMissingSourceRowsEvent","p":"Drush/Drupal/Migrate/MigrateMissingSourceRowsEvent"},{"l":4,"n":"MigratePrepareRowEvent","p":"Drush/Drupal/Migrate/MigratePrepareRowEvent"},{"l":4,"n":"MigrateUtils","p":"Drush/Drupal/Migrate/MigrateUtils"}]},{"l":3,"n":"DrupalKernel","p":"Drush/Drupal/DrupalKernel"},{"l":3,"n":"DrupalKernelTrait","p":"Drush/Drupal/DrupalKernelTrait"},{"l":3,"n":"DrupalUtil","p":"Drush/Drupal/DrupalUtil"},{"l":3,"n":"DrushLoggerServiceProvider","p":"Drush/Drupal/DrushLoggerServiceProvider"},{"l":3,"n":"DrushServiceModifier","p":"Drush/Drupal/DrushServiceModifier"},{"l":3,"n":"ExtensionDiscovery","p":"Drush/Drupal/ExtensionDiscovery"},{"l":3,"n":"FindCommandsCompilerPass","p":"Drush/Drupal/FindCommandsCompilerPass"},{"l":3,"n":"InstallerKernel","p":"Drush/Drupal/InstallerKernel"},{"l":3,"n":"UpdateKernel","p":"Drush/Drupal/UpdateKernel"}]},{"l":2,"n":"Exceptions","p":"Drush/Exceptions","c":[{"l":3,"n":"CommandFailedException","p":"Drush/Exceptions/CommandFailedException"},{"l":3,"n":"UserAbortException","p":"Drush/Exceptions/UserAbortException"}]},{"l":2,"n":"Exec","p":"Drush/Exec","c":[{"l":3,"n":"ExecTrait","p":"Drush/Exec/ExecTrait"}]},{"l":2,"n":"Formatters","p":"Drush/Formatters","c":[{"l":3,"n":"DrushFormatterManager","p":"Drush/Formatters/DrushFormatterManager"}]},{"l":2,"n":"Log","p":"Drush/Log","c":[{"l":3,"n":"DrushLog","p":"Drush/Log/DrushLog"},{"l":3,"n":"DrushLoggerManager","p":"Drush/Log/DrushLoggerManager"},{"l":3,"n":"LogLevel","p":"Drush/Log/LogLevel"},{"l":3,"n":"Logger","p":"Drush/Log/Logger"},{"l":3,"n":"SuccessInterface","p":"Drush/Log/SuccessInterface"}]},{"l":2,"n":"Preflight","p":"Drush/Preflight","c":[{"l":3,"n":"ArgsPreprocessor","p":"Drush/Preflight/ArgsPreprocessor"},{"l":3,"n":"ArgsRemapper","p":"Drush/Preflight/ArgsRemapper"},{"l":3,"n":"LegacyPreflight","p":"Drush/Preflight/LegacyPreflight"},{"l":3,"n":"Preflight","p":"Drush/Preflight/Preflight"},{"l":3,"n":"PreflightArgs","p":"Drush/Preflight/PreflightArgs"},{"l":3,"n":"PreflightArgsInterface","p":"Drush/Preflight/PreflightArgsInterface"},{"l":3,"n":"PreflightLog","p":"Drush/Preflight/PreflightLog"},{"l":3,"n":"PreflightSiteLocator","p":"Drush/Preflight/PreflightSiteLocator"},{"l":3,"n":"PreflightVerify","p":"Drush/Preflight/PreflightVerify"},{"l":3,"n":"RedispatchToSiteLocal","p":"Drush/Preflight/RedispatchToSiteLocal"}]},{"l":2,"n":"Psysh","p":"Drush/Psysh","c":[{"l":3,"n":"Caster","p":"Drush/Psysh/Caster"},{"l":3,"n":"DrushCommand","p":"Drush/Psysh/DrushCommand"},{"l":3,"n":"DrushHelpCommand","p":"Drush/Psysh/DrushHelpCommand"},{"l":3,"n":"Shell","p":"Drush/Psysh/Shell"}]},{"l":2,"n":"Runtime","p":"Drush/Runtime","c":[{"l":3,"n":"DependencyInjection","p":"Drush/Runtime/DependencyInjection"},{"l":3,"n":"ErrorHandler","p":"Drush/Runtime/ErrorHandler"},{"l":3,"n":"HandlerInterface","p":"Drush/Runtime/HandlerInterface"},{"l":3,"n":"RedispatchHook","p":"Drush/Runtime/RedispatchHook"},{"l":3,"n":"Runtime","p":"Drush/Runtime/Runtime"},{"l":3,"n":"ShutdownHandler","p":"Drush/Runtime/ShutdownHandler"},{"l":3,"n":"TildeExpansionHook","p":"Drush/Runtime/TildeExpansionHook"}]},{"l":2,"n":"SiteAlias","p":"Drush/SiteAlias","c":[{"l":3,"n":"Util","p":"Drush/SiteAlias/Util","c":[{"l":4,"n":"InternalYamlDataFileLoader","p":"Drush/SiteAlias/Util/InternalYamlDataFileLoader"}]},{"l":3,"n":"HostPath","p":"Drush/SiteAlias/HostPath"},{"l":3,"n":"LegacyAliasConverter","p":"Drush/SiteAlias/LegacyAliasConverter"},{"l":3,"n":"ProcessManager","p":"Drush/SiteAlias/ProcessManager"},{"l":3,"n":"SiteAliasFileLoader","p":"Drush/SiteAlias/SiteAliasFileLoader"},{"l":3,"n":"SiteAliasManagerAwareInterface","p":"Drush/SiteAlias/SiteAliasManagerAwareInterface"},{"l":3,"n":"SiteAliasName","p":"Drush/SiteAlias/SiteAliasName"},{"l":3,"n":"SiteSpecParser","p":"Drush/SiteAlias/SiteSpecParser"}]},{"l":2,"n":"Sql","p":"Drush/Sql","c":[{"l":3,"n":"SqlBase","p":"Drush/Sql/SqlBase"},{"l":3,"n":"SqlException","p":"Drush/Sql/SqlException"},{"l":3,"n":"SqlMysql","p":"Drush/Sql/SqlMysql"},{"l":3,"n":"SqlPgsql","p":"Drush/Sql/SqlPgsql"},{"l":3,"n":"SqlSqlite","p":"Drush/Sql/SqlSqlite"},{"l":3,"n":"SqlTableSelectionTrait","p":"Drush/Sql/SqlTableSelectionTrait"}]},{"l":2,"n":"Symfony","p":"Drush/Symfony","c":[{"l":3,"n":"BootstrapCompilerPass","p":"Drush/Symfony/BootstrapCompilerPass"},{"l":3,"n":"DrushArgvInput","p":"Drush/Symfony/DrushArgvInput"},{"l":3,"n":"DrushStyleInjector","p":"Drush/Symfony/DrushStyleInjector"}]},{"l":2,"n":"TestTraits","p":"Drush/TestTraits","c":[{"l":3,"n":"CliTestTrait","p":"Drush/TestTraits/CliTestTrait"},{"l":3,"n":"DrushTestTrait","p":"Drush/TestTraits/DrushTestTrait"},{"l":3,"n":"OutputUtilsTrait","p":"Drush/TestTraits/OutputUtilsTrait"}]},{"l":2,"n":"Utils","p":"Drush/Utils","c":[{"l":3,"n":"FsUtils","p":"Drush/Utils/FsUtils"},{"l":3,"n":"StringUtils","p":"Drush/Utils/StringUtils"}]},{"l":2,"n":"Application","p":"Drush/Application"},{"l":2,"n":"Drush","p":"Drush/Drush"}]}]},"treeOpenLevel":2},
    /** @var boolean */
    treeLoaded: false,
    /** @var boolean */
    listenersRegistered: false,
    autoCompleteData: null,
    /** @var boolean */
    autoCompleteLoading: false,
    /** @var boolean */
    autoCompleteLoaded: false,
    /** @var string|null */
    rootPath: null,
    /** @var string|null */
    autoCompleteDataUrl: null,
    /** @var HTMLElement|null */
    doctumSearchAutoComplete: null,
    /** @var HTMLElement|null */
    doctumSearchAutoCompleteProgressBarContainer: null,
    /** @var HTMLElement|null */
    doctumSearchAutoCompleteProgressBar: null,
    /** @var number */
    doctumSearchAutoCompleteProgressBarPercent: 0,
    /** @var autoComplete|null */
    autoCompleteJS: null,
    querySearchSecurityRegex: /([^0-9a-zA-Z:\\\\_\s])/gi,
    buildTreeNode: function (treeNode, htmlNode, treeOpenLevel) {
        var ulNode = document.createElement('ul');
        for (var childKey in treeNode.c) {
            var child = treeNode.c[childKey];
            var liClass = document.createElement('li');
            var hasChildren = child.hasOwnProperty('c');
            var nodeSpecialName = (hasChildren ? 'namespace:' : 'class:') + child.p.replace(/\//g, '_');
            liClass.setAttribute('data-name', nodeSpecialName);

            // Create the node that will have the text
            var divHd = document.createElement('div');
            var levelCss = child.l - 1;
            divHd.className = hasChildren ? 'hd' : 'hd leaf';
            divHd.style.paddingLeft = (hasChildren ? (levelCss * 18) : (8 + (levelCss * 18))) + 'px';
            if (hasChildren) {
                if (child.l <= treeOpenLevel) {
                    liClass.className = 'opened';
                }
                var spanIcon = document.createElement('span');
                spanIcon.className = 'icon icon-play';
                divHd.appendChild(spanIcon);
            }
            var aLink = document.createElement('a');

            // Edit the HTML link to work correctly based on the current depth
            aLink.href = Doctum.rootPath + child.p + '.html';
            aLink.innerText = child.n;
            divHd.appendChild(aLink);
            liClass.appendChild(divHd);

            // It has children
            if (hasChildren) {
                var divBd = document.createElement('div');
                divBd.className = 'bd';
                Doctum.buildTreeNode(child, divBd, treeOpenLevel);
                liClass.appendChild(divBd);
            }
            ulNode.appendChild(liClass);
        }
        htmlNode.appendChild(ulNode);
    },
    initListeners: function () {
        if (Doctum.listenersRegistered) {
            // Quick exit, already registered
            return;
        }
                Doctum.listenersRegistered = true;
    },
    loadTree: function () {
        if (Doctum.treeLoaded) {
            // Quick exit, already registered
            return;
        }
        Doctum.rootPath = document.body.getAttribute('data-root-path');
        Doctum.buildTreeNode(Doctum.treeJson.tree, document.getElementById('api-tree'), Doctum.treeJson.treeOpenLevel);

        // Toggle left-nav divs on click
        $('#api-tree .hd span').on('click', function () {
            $(this).parent().parent().toggleClass('opened');
        });

        // Expand the parent namespaces of the current page.
        var expected = $('body').attr('data-name');

        if (expected) {
            // Open the currently selected node and its parents.
            var container = $('#api-tree');
            var node = $('#api-tree li[data-name="' + expected + '"]');
            // Node might not be found when simulating namespaces
            if (node.length > 0) {
                node.addClass('active').addClass('opened');
                node.parents('li').addClass('opened');
                var scrollPos = node.offset().top - container.offset().top + container.scrollTop();
                // Position the item nearer to the top of the screen.
                scrollPos -= 200;
                container.scrollTop(scrollPos);
            }
        }
        Doctum.treeLoaded = true;
    },
    pagePartiallyLoaded: function (event) {
        Doctum.initListeners();
        Doctum.loadTree();
        Doctum.loadAutoComplete();
    },
    pageFullyLoaded: function (event) {
        // it may not have received DOMContentLoaded event
        Doctum.initListeners();
        Doctum.loadTree();
        Doctum.loadAutoComplete();
        // Fire the event in the search page too
        if (typeof DoctumSearch === 'object') {
            DoctumSearch.pageFullyLoaded();
        }
    },
    loadAutoComplete: function () {
        if (Doctum.autoCompleteLoaded) {
            // Quick exit, already loaded
            return;
        }
        Doctum.autoCompleteDataUrl = document.body.getAttribute('data-search-index-url');
        Doctum.doctumSearchAutoComplete = document.getElementById('doctum-search-auto-complete');
        Doctum.doctumSearchAutoCompleteProgressBarContainer = document.getElementById('search-progress-bar-container');
        Doctum.doctumSearchAutoCompleteProgressBar = document.getElementById('search-progress-bar');
        if (Doctum.doctumSearchAutoComplete !== null) {
            // Wait for it to be loaded
            Doctum.doctumSearchAutoComplete.addEventListener('init', function (_) {
                Doctum.autoCompleteLoaded = true;
                Doctum.doctumSearchAutoComplete.addEventListener('selection', function (event) {
                    // Go to selection page
                    window.location = Doctum.rootPath + event.detail.selection.value.p;
                });
                Doctum.doctumSearchAutoComplete.addEventListener('navigate', function (event) {
                    // Set selection in text box
                    if (typeof event.detail.selection.value === 'object') {
                        Doctum.doctumSearchAutoComplete.value = event.detail.selection.value.n;
                    }
                });
                Doctum.doctumSearchAutoComplete.addEventListener('results', function (event) {
                    Doctum.markProgressFinished();
                });
            });
        }
        // Check if the lib is loaded
        if (typeof autoComplete === 'function') {
            Doctum.bootAutoComplete();
        }
    },
    markInProgress: function () {
            Doctum.doctumSearchAutoCompleteProgressBarContainer.className = 'search-bar';
            Doctum.doctumSearchAutoCompleteProgressBar.className = 'progress-bar indeterminate';
            if (typeof DoctumSearch === 'object' && DoctumSearch.pageFullyLoaded) {
                DoctumSearch.doctumSearchPageAutoCompleteProgressBarContainer.className = 'search-bar';
                DoctumSearch.doctumSearchPageAutoCompleteProgressBar.className = 'progress-bar indeterminate';
            }
    },
    markProgressFinished: function () {
        Doctum.doctumSearchAutoCompleteProgressBarContainer.className = 'search-bar hidden';
        Doctum.doctumSearchAutoCompleteProgressBar.className = 'progress-bar';
        if (typeof DoctumSearch === 'object' && DoctumSearch.pageFullyLoaded) {
            DoctumSearch.doctumSearchPageAutoCompleteProgressBarContainer.className = 'search-bar hidden';
            DoctumSearch.doctumSearchPageAutoCompleteProgressBar.className = 'progress-bar';
        }
    },
    makeProgess: function () {
        Doctum.makeProgressOnProgressBar(
            Doctum.doctumSearchAutoCompleteProgressBarPercent,
            Doctum.doctumSearchAutoCompleteProgressBar
        );
        if (typeof DoctumSearch === 'object' && DoctumSearch.pageFullyLoaded) {
            Doctum.makeProgressOnProgressBar(
                Doctum.doctumSearchAutoCompleteProgressBarPercent,
                DoctumSearch.doctumSearchPageAutoCompleteProgressBar
            );
        }
    },
    loadAutoCompleteData: function (query) {
        return new Promise(function (resolve, reject) {
            if (Doctum.autoCompleteData !== null) {
                resolve(Doctum.autoCompleteData);
                return;
            }
            Doctum.markInProgress();
            function reqListener() {
                Doctum.autoCompleteLoading = false;
                Doctum.autoCompleteData = JSON.parse(this.responseText).items;
                Doctum.markProgressFinished();

                setTimeout(function () {
                    resolve(Doctum.autoCompleteData);
                }, 50);// Let the UI render once before sending the results for processing. This gives time to the progress bar to hide
            }
            function reqError(err) {
                Doctum.autoCompleteLoading = false;
                Doctum.autoCompleteData = null;
                console.error(err);
                reject(err);
            }

            var oReq = new XMLHttpRequest();
            oReq.onload = reqListener;
            oReq.onerror = reqError;
            oReq.onprogress = function (pe) {
                if (pe.lengthComputable) {
                    Doctum.doctumSearchAutoCompleteProgressBarPercent = parseInt(pe.loaded / pe.total * 100, 10);
                    Doctum.makeProgess();
                }
            };
            oReq.onloadend = function (_) {
                Doctum.markProgressFinished();
            };
            oReq.open('get', Doctum.autoCompleteDataUrl, true);
            oReq.send();
        });
    },
    /**
     * Make some progress on a progress bar
     *
     * @param number percentage
     * @param HTMLElement progressBar
     * @return void
     */
    makeProgressOnProgressBar: function(percentage, progressBar) {
        progressBar.className = 'progress-bar';
        progressBar.style.width = percentage + '%';
        progressBar.setAttribute(
            'aria-valuenow', percentage
        );
    },
    searchEngine: function (query, record) {
        if (typeof query !== 'string') {
            return '';
        }
        // replace all (mode = g) spaces and non breaking spaces (\s) by pipes
        // g = global mode to mark also the second word searched
        // i = case insensitive
        // how this function works:
        // First: search if the query has the keywords in sequence
        // Second: replace the keywords by a mark and leave all the text in between non marked
        
        if (record.match(new RegExp('(' + query.replace(/\s/g, ').*(') + ')', 'gi')) === null) {
            return '';// Does not match
        }

        var replacedRecord = record.replace(new RegExp('(' + query.replace(/\s/g, '|') + ')', 'gi'), function (group) {
            return '<mark class="auto-complete-highlight">' + group + '</mark>';
        });

        if (replacedRecord !== record) {
            return replacedRecord;// This should not happen but just in case there was no match done
        }

        return '';
    },
    /**
     * Clean the search query
     *
     * @param string|null query
     * @return string
     */
    cleanSearchQuery: function (query) {
        if (typeof query !== 'string') {
            return '';
        }
        // replace any chars that could lead to injecting code in our regex
        // remove start or end spaces
        // replace backslashes by an escaped version, use case in search: \myRootFunction
        return query.replace(Doctum.querySearchSecurityRegex, '').trim().replace(/\\/g, '\\\\');
    },
    bootAutoComplete: function () {
        Doctum.autoCompleteJS = new autoComplete(
            {
                selector: '#doctum-search-auto-complete',
                searchEngine: function (query, record) {
                    return Doctum.searchEngine(query, record);
                },
                submit: true,
                data: {
                    src: function (q) {
                        Doctum.markInProgress();
                        return Doctum.loadAutoCompleteData(q);
                    },
                    keys: ['n'],// Data 'Object' key to be searched
                    cache: false, // Is not compatible with async fetch of data
                },
                query: (input) => {
                    return Doctum.cleanSearchQuery(input);
                },
                trigger: (query) => {
                    return Doctum.cleanSearchQuery(query).length > 0;
                },
                resultsList: {
                    tag: 'ul',
                    class: 'auto-complete-dropdown-menu',
                    destination: '#auto-complete-results',
                    position: 'afterbegin',
                    maxResults: 500,
                    noResults: false,
                },
                resultItem: {
                    tag: 'li',
                    class: 'auto-complete-result',
                    highlight: 'auto-complete-highlight',
                    selected: 'auto-complete-selected'
                },
            }
        );
    }
};


document.addEventListener('DOMContentLoaded', Doctum.pagePartiallyLoaded, false);
window.addEventListener('load', Doctum.pageFullyLoaded, false);
