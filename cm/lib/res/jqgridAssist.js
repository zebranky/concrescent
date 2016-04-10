var gridParams =
{

    default : {
        // Ajax related configurations
        datatype : "json",
        mtype: "POST",
        cache: false,
        caption: "",
        // Grid total width and height of parent
        width: '100%',
        height: 'auto',
        sortable: false,
        shringToFit: false,
        // Paging show at top
        toppager: true,
        rowNum: 10,
        rowList: [5, 10, 20, 50, 100, 200, 1000],
        viewrecords: true, //Specify if "total number of records" is displayed
    },
    "defaultPager": {
        parameters: {
            refresh: true,
            add: false,
            edit: false,
            del: false,
            search: false,
            view: false
        },
        prmEdit: {},
        prmAdd: {},
        prmDel: {},
        prmSearch: {},
        prmView: {}
    }

}

//jqGrid functions
function loadGrid(target, customParams, customPagerParams) {
    //Merge in parameters from the defaults specified in gridParams, then the defaults according to the page params, then whatever might be passed in customParams
    var parameters = $.extend(true, {}, gridParams["default"], gridParams[target], customParams);
    //Do the same for the pager
    var pagerParameters = $.extend(true, {}, gridParams["defaultPager"], gridParams[parameters["pager"]], customPagerParams);
    var grid = $(target);
    //showLoading(true);

    //Init the grid, and call a refresh
    grid.jqGrid(parameters).setGridParam(customParams).navGrid(parameters["pager"],
        pagerParameters["parameters"],
        pagerParameters["prmEdit"],
        pagerParameters["prmAdd"],
        pagerParameters["prmDel"],
        pagerParameters["prmSearch"],
        pagerParameters["prmView"]
    );
    if (pagerParameters["navGridButtons"] !== undefined)
        $.each(pagerParameters["navGridButtons"], function (idx, item) {
            grid.navButtonAdd(parameters["pager"], item);
        })
    grid.trigger('reloadGrid');
    //responsive_jqgrid($(target));
    if (parameters["url"] == "clientArray" || parameters["url"] == undefined || parameters["url"] == "") {
        //showLoading(false);
    }
    return grid;
}
