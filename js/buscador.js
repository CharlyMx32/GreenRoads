function executeSearch(search, searchElement, displayType) {
    console.log(searchElement);
    var busquedadividida = search.replace(/ /g, "'):containsi('")

    $.extend($.expr[':'], {
        'containsi': function(elem, i, match, array) {
            return (elem.textContent || elem.innerText || '').toLowerCase()
                .indexOf((match[3] || "").toLowerCase()) >= 0;
        }
    });

    $(`${searchElement}`).not(`:containsi('${busquedadividida})`).each(function(e) {
        $(this).css('display', 'none');
        $(this).removeClass('count');
    });

    $(`${searchElement}:containsi('${busquedadividida}')`).each(function(e) {
        $(this).css('display', displayType);
        $(this).addClass('count');
    });

    $('.resultados').html('Mostrando <b>' + $('.count').length + '</b> de <b>' + $(searchElement).length +'</b> resultados');
}

function setSearcher(params) {
    
    if(!params.hasOwnProperty('display_type')) params.display_type = "block";

    executeSearch(
        "", 
        params.search_element,
        params.display_type
    );

    document.querySelector(params.input).addEventListener('keyup', e => {
        executeSearch(
            e.target.value, 
            params.search_element,
            params.display_type
        );
    });

}