var Doctum = {
    treeJson: {"tree":{"l":0,"n":"","p":"","c":[{"l":1,"n":"Drush","p":"Drush","c":[{"l":2,"n":"Commands","p":"Drush/Commands","c":[{"l":3,"n":"cache","p":"Drush/Commands/cache","c":[{"l":4,"n":"CacheClearCommand","p":"Drush/Commands/cache/CacheClearCommand"},{"l":4,"n":"CacheGetCommand","p":"Drush/Commands/cache/CacheGetCommand"},{"l":4,"n":"CacheSetCommand","p":"Drush/Commands/cache/CacheSetCommand"},{"l":4,"n":"CacheTagsCommand","p":"Drush/Commands/cache/CacheTagsCommand"},{"l":4,"n":"CacheTrait","p":"Drush/Commands/cache/CacheTrait"},{"l":4,"n":"CacheWarmCommand","p":"Drush/Commands/cache/CacheWarmCommand"}]},{"l":3,"n":"config","p":"Drush/Commands/config","c":[{"l":4,"n":"ConfigDeleteCommand","p":"Drush/Commands/config/ConfigDeleteCommand"},{"l":4,"n":"ConfigGetCommand","p":"Drush/Commands/config/ConfigGetCommand"},{"l":4,"n":"ConfigNameTrait","p":"Drush/Commands/config/ConfigNameTrait"},{"l":4,"n":"ConfigSetCommand","p":"Drush/Commands/config/ConfigSetCommand"},{"l":4,"n":"ConfigStatusCommand","p":"Drush/Commands/config/ConfigStatusCommand"}]},{"l":3,"n":"core","p":"Drush/Commands/core","c":[{"l":4,"n":"BrowseCommand","p":"Drush/Commands/core/BrowseCommand"},{"l":4,"n":"DependencyTreeTrait","p":"Drush/Commands/core/DependencyTreeTrait"},{"l":4,"n":"ImageDeriveCommand","p":"Drush/Commands/core/ImageDeriveCommand"},{"l":4,"n":"ImageFlushCommand","p":"Drush/Commands/core/ImageFlushCommand"},{"l":4,"n":"ImageTrait","p":"Drush/Commands/core/ImageTrait"},{"l":4,"n":"JsonapiGetCommand","p":"Drush/Commands/core/JsonapiGetCommand"},{"l":4,"n":"MkDocsCommand","p":"Drush/Commands/core/MkDocsCommand"},{"l":4,"n":"PhpEvalCommand","p":"Drush/Commands/core/PhpEvalCommand"},{"l":4,"n":"PhpScriptCommand","p":"Drush/Commands/core/PhpScriptCommand"},{"l":4,"n":"RouteCommand","p":"Drush/Commands/core/RouteCommand"},{"l":4,"n":"WhyConfigCommand","p":"Drush/Commands/core/WhyConfigCommand"},{"l":4,"n":"WhyModuleCommand","p":"Drush/Commands/core/WhyModuleCommand"},{"l":4,"n":"WorkspacePublishCommand","p":"Drush/Commands/core/WorkspacePublishCommand"}]},{"l":3,"n":"entity","p":"Drush/Commands/entity","c":[{"l":4,"n":"EntityDeleteCommand","p":"Drush/Commands/entity/EntityDeleteCommand"},{"l":4,"n":"EntitySaveCommand","p":"Drush/Commands/entity/EntitySaveCommand"},{"l":4,"n":"EntityTrait","p":"Drush/Commands/entity/EntityTrait"}]},{"l":3,"n":"queue","p":"Drush/Commands/queue","c":[{"l":4,"n":"QueueDeleteCommand","p":"Drush/Commands/queue/QueueDeleteCommand"},{"l":4,"n":"QueueListCommand","p":"Drush/Commands/queue/QueueListCommand"},{"l":4,"n":"QueueRunCommand","p":"Drush/Commands/queue/QueueRunCommand"},{"l":4,"n":"QueueTrait","p":"Drush/Commands/queue/QueueTrait"}]},{"l":3,"n":"role","p":"Drush/Commands/role","c":[{"l":4,"n":"RoleCreateCommand","p":"Drush/Commands/role/RoleCreateCommand"},{"l":4,"n":"RoleDeleteCommand","p":"Drush/Commands/role/RoleDeleteCommand"},{"l":4,"n":"RoleListCommand","p":"Drush/Commands/role/RoleListCommand"},{"l":4,"n":"RolePermAddCommand","p":"Drush/Commands/role/RolePermAddCommand"},{"l":4,"n":"RolePermRemoveCommand","p":"Drush/Commands/role/RolePermRemoveCommand"},{"l":4,"n":"RoleTrait","p":"Drush/Commands/role/RoleTrait"}]},{"l":3,"n":"state","p":"Drush/Commands/state","c":[{"l":4,"n":"StateDeleteCommand","p":"Drush/Commands/state/StateDeleteCommand"},{"l":4,"n":"StateGetCommand","p":"Drush/Commands/state/StateGetCommand"},{"l":4,"n":"StateSetCommand","p":"Drush/Commands/state/StateSetCommand"},{"l":4,"n":"StateTrait","p":"Drush/Commands/state/StateTrait"}]},{"l":3,"n":"user","p":"Drush/Commands/user","c":[{"l":4,"n":"UserBlockCommand","p":"Drush/Commands/user/UserBlockCommand"},{"l":4,"n":"UserCancelCommand","p":"Drush/Commands/user/UserCancelCommand"},{"l":4,"n":"UserCreateCommand","p":"Drush/Commands/user/UserCreateCommand"},{"l":4,"n":"UserInformationCommand","p":"Drush/Commands/user/UserInformationCommand"},{"l":4,"n":"UserPasswordCommand","p":"Drush/Commands/user/UserPasswordCommand"},{"l":4,"n":"UserRoleAddCommand","p":"Drush/Commands/user/UserRoleAddCommand"},{"l":4,"n":"UserRoleRemoveCommand","p":"Drush/Commands/user/UserRoleRemoveCommand"},{"l":4,"n":"UserTrait","p":"Drush/Commands/user/UserTrait"},{"l":4,"n":"UserUnblockCommand","p":"Drush/Commands/user/UserUnblockCommand"}]},{"l":3,"n":"watchdog","p":"Drush/Commands/watchdog","c":[{"l":4,"n":"WatchdogDeleteCommand","p":"Drush/Commands/watchdog/WatchdogDeleteCommand"},{"l":4,"n":"WatchdogListCommand","p":"Drush/Commands/watchdog/WatchdogListCommand"},{"l":4,"n":"WatchdogShowCommand","p":"Drush/Commands/watchdog/WatchdogShowCommand"},{"l":4,"n":"WatchdogShowOneCommand","p":"Drush/Commands/watchdog/WatchdogShowOneCommand"},{"l":4,"n":"WatchdogTailCommand","p":"Drush/Commands/watchdog/WatchdogTailCommand"},{"l":4,"n":"WatchdogTrait","p":"Drush/Commands/watchdog/WatchdogTrait"}]}]},{"l":2,"n":"Doctor","p":"Drush/Doctor","c":[{"l":3,"n":"Commands","p":"Drush/Doctor/Commands","c":[{"l":4,"n":"config","p":"Drush/Doctor/Commands/config","c":[{"l":5,"n":"ConfigCommandTrait","p":"Drush/Doctor/Commands/config/ConfigCommandTrait"},{"l":5,"n":"ConfigExportCommand","p":"Drush/Doctor/Commands/config/ConfigExportCommand"},{"l":5,"n":"ConfigImportCommand","p":"Drush/Doctor/Commands/config/ConfigImportCommand"}]},{"l":4,"n":"deploy","p":"Drush/Doctor/Commands/deploy","c":[{"l":5,"n":"DeployCommand","p":"Drush/Doctor/Commands/deploy/DeployCommand"},{"l":5,"n":"DeployHookCommand","p":"Drush/Doctor/Commands/deploy/DeployHookCommand"},{"l":5,"n":"DeployHookStatusCommand","p":"Drush/Doctor/Commands/deploy/DeployHookStatusCommand"},{"l":5,"n":"DeployMarkCompleteCommand","p":"Drush/Doctor/Commands/deploy/DeployMarkCompleteCommand"},{"l":5,"n":"DeployTrait","p":"Drush/Doctor/Commands/deploy/DeployTrait"}]},{"l":4,"n":"ex","p":"Drush/Doctor/Commands/ex","c":[{"l":5,"n":"ExtensionInstallCommand","p":"Drush/Doctor/Commands/ex/ExtensionInstallCommand"},{"l":5,"n":"ExtensionUninstallCommand","p":"Drush/Doctor/Commands/ex/ExtensionUninstallCommand"}]},{"l":4,"n":"maint","p":"Drush/Doctor/Commands/maint","c":[{"l":5,"n":"MaintDisableCommand","p":"Drush/Doctor/Commands/maint/MaintDisableCommand"},{"l":5,"n":"MaintEnableCommand","p":"Drush/Doctor/Commands/maint/MaintEnableCommand"},{"l":5,"n":"MaintStatusCommand","p":"Drush/Doctor/Commands/maint/MaintStatusCommand"}]},{"l":4,"n":"sql","p":"Drush/Doctor/Commands/sql","c":[{"l":5,"n":"SqlCliCommand","p":"Drush/Doctor/Commands/sql/SqlCliCommand"},{"l":5,"n":"SqlConnectCommand","p":"Drush/Doctor/Commands/sql/SqlConnectCommand"},{"l":5,"n":"SqlDropCommand","p":"Drush/Doctor/Commands/sql/SqlDropCommand"},{"l":5,"n":"SqlDumpCommand","p":"Drush/Doctor/Commands/sql/SqlDumpCommand"},{"l":5,"n":"SqlQueryCommand","p":"Drush/Doctor/Commands/sql/SqlQueryCommand"},{"l":5,"n":"SqlTrait","p":"Drush/Doctor/Commands/sql/SqlTrait"}]},{"l":4,"n":"updatedb","p":"Drush/Doctor/Commands/updatedb","c":[{"l":5,"n":"UpdatedbCommand","p":"Drush/Doctor/Commands/updatedb/UpdatedbCommand"},{"l":5,"n":"UpdatedbStatusCommand","p":"Drush/Doctor/Commands/updatedb/UpdatedbStatusCommand"},{"l":5,"n":"UpdatedbTrait","p":"Drush/Doctor/Commands/updatedb/UpdatedbTrait"}]},{"l":4,"n":"ConsoleTrait","p":"Drush/Doctor/Commands/ConsoleTrait"}]},{"l":3,"n":"Sql","p":"Drush/Doctor/Sql","c":[{"l":4,"n":"Client","p":"Drush/Doctor/Sql/Client"},{"l":4,"n":"DumpOptions","p":"Drush/Doctor/Sql/DumpOptions"},{"l":4,"n":"Mysql","p":"Drush/Doctor/Sql/Mysql"},{"l":4,"n":"Pgsql","p":"Drush/Doctor/Sql/Pgsql"},{"l":4,"n":"Sqlite","p":"Drush/Doctor/Sql/Sqlite"},{"l":4,"n":"TableSelection","p":"Drush/Doctor/Sql/TableSelection"}]},{"l":3,"n":"DoctorServiceProvider","p":"Drush/Doctor/DoctorServiceProvider"},{"l":3,"n":"FindClasses","p":"Drush/Doctor/FindClasses"}]},{"l":2,"n":"Event","p":"Drush/Event","c":[{"l":3,"n":"CacheClearEvent","p":"Drush/Event/CacheClearEvent"}]},{"l":2,"n":"Utils","p":"Drush/Utils","c":[{"l":3,"n":"StringUtils","p":"Drush/Utils/StringUtils"}]}]}]},"treeOpenLevel":2},
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
