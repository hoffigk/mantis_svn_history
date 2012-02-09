// ==UserScript==
// @name       Mantis SVN History Information
// @version    0.1
// @description  Injects svn history informations
// @include    */mantis/view.php*
// @copyright  2011+, You
// @require    http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js
// ==/UserScript==

var console = unsafeWindow.console;

(function($)
{
    var mantis_ticket_number = document.title.substr(0, 7); 
    
    var mantis_svn_history_url = 'http://127.0.0.1/mantis_svn_history/pub/index.php';
    var svn_repository_url = 'http://subversion.yourhost.com/svn/yourprojectname/trunk';
    
    var get_svn_history_dom_element = function()
    {
        var html = '<div id="svn_history">'
                + '     <table cellspacing="1" class="width100">'
                + '         <tr>'
                + '             <td class="form-title" width="15%" colspan="5">'
                + '                 <a href="javascript:;" onclick="ToggleDiv(\'svn_history\'); return false;"><img border="0" alt="-" src="images/minus.png"></a>'
                + '                 SVN History'
                + '             </td>'
                + '         </tr>'
                + '         <tr class="row-category-history">'
                + '             <td class="small-caption">Date</td>'
                + '             <td class="small-caption">Version</td>'
                + '             <td class="small-caption">Author</td>'
                + '             <td class="small-caption">Message</td>'
                + '             <td class="small-caption">Files</td>'
                + '         </tr>'
                + '         <tr class="js-login" style="display: none">'
                + '             <td colspan="5">'
                + '                 <form>'
                + '                     <label for="">Mantis SVN App URL: <input type="text" name="mantis_svn_app_url"/></label>'
                + '                     <label for="">SVN Repository URL: <input type="text" name="svn_repository_url"/></label>'
                + '                     <label for="">SVN Username: <input type="text" name="svn_username"/></label>'
                + '                     <label for="">SVN Password: <input type="password" name="svn_password"/></label>'
                + '                     <button type="submit">Login</button>'
                + '                 </form>'
                + '             </td>'
                + '         </tr>'
                + '     </table>'
                + ' </div>'
        
        return $(html);
    }
    
    var build_list = function(data)
    {
        var table = get_svn_history_dom_element();
        
        $.each(data, function(i,item)
        {
            var date = new Date(item.date);
            var month = date.getMonth();
            var day = date.getDay();
            var hours = date.getHours();
            var minutes = date.getMinutes();
            
            var date_string = date.getFullYear()
                + '-' + ((month < 10) ? '0' + month : month)
                + '-' + ((day < 10) ? '0' + day : day)
                + '<br />'
                + ' ' + ((hours < 10) ? '0' + hours : hours)
                + ':'
                +  ((minutes < 10) ? '0' + minutes : minutes)
                ;
            
            var ul = $('<ul />')
                .css('padding', 0)
                .css('margin', 0)
                //.css('overflow-y', 'auto')
                //.css('white-space', 'nowrap')
                .css('list-style', 'none');
            
            $.each(item.paths, function(i, path)
            {
                ul.append($('<li />')
                    .append($('<span />').html(path.action + "&nbsp;"))
                    .append($('<span />').html(path.path)));
            });
            
            $(table).find('table').append(
                $('<tr />')
                    .css('vertical-align', 'top')
                    .addClass((i%2 === 0) ? 'row-1' : 'row-2')
                    .append($('<td />').html(date_string))
                    .append($('<td />').html(item.revision))
                    .append($('<td />').html(item.author))
                    .append($('<td />').html(item.msg))
                    .append($('<td />').append(ul))     
            );
        });
        
        $(table).prependTo("#relationships_open"); 
    }
    
    var show_login = function()
    {
        $('#svn_history tr[class=js-login]').show();
        
        $('#svn_history form').submit(function()
        {
            GM_xmlhttpRequest({
                'method': "POST",
                'url': mantis_svn_history_url,
                'data': $(this).serialize(),
                'onload': function(resp)
                {
                    alert(resp);
                }
            });
            
            return false;
        });      
    }
    
    get_svn_history_dom_element().prependTo("#relationships_open");
    
    if (false)
    {
        show_login();
    }
   
    GM_xmlhttpRequest({
        'method': "GET",
        'url': mantis_svn_history_url + '?svn_repository_url=' + encodeURIComponent(svn_repository_url) + '&mantis_ticket_number=' + mantis_ticket_number,
        'onload': function(resp)
        {
            build_list($.parseJSON(resp.responseText));
        }
    });
    
    // var Cookie = function(c,d,e){e='';for(d in c)c.hasOwnProperty(d)&&(e+=(e?'; ':e)+d+'='+c[d]);return''+c!==c?e:(document.cookie.match(c+'=(.+?);')||0)[1]}
    // setting a Cookie:
    // document.cookie = C({cookiename: 'testcookie', expires: (new Date(new Date()*1+6E10)).toGMTString()});
    // C('cookiename') returns the still escaped value of the Cookie "cookiename"
    
    
})(jQuery);
