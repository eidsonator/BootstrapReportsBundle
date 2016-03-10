/**
 * Created by teidson on 8/17/2015.
 */

    //typeahead report search
(function() {
    var data = new Dataset({
        prefetch: {
            url: Routing.generate('eidsonator_reports_list_json'),
            ttl: 0
        },
        valueKey: 'name',
        sorter: function(a,b) {
            var val = $('form[role="search"] input.search-query').typeahead('val')[0];

            //beginning of title match
            var beg = new RegExp('^'+val,'i');
            //word boundary match
            var word = new RegExp('\b'+val,'i');

            //weights for components of the sort algorithm
            var popweight = 2;
            var wordweight = 10;
            var begweight = 15;

            //popularity
            var popa = a.popularity;
            var popb = b.popularity;

            //beginning of string match
            var bega = beg.test(a.name);
            var begb = beg.test(b.name);

            //beginning of word match
            var worda = !bega && word.test(a.name);
            var wordb = !begb && word.test(b.name);

            //determine score
            var scorea = popa*popweight + bega*begweight + worda*wordweight;
            var scoreb = popb*popweight + begb*begweight + wordb*wordweight;

            return scoreb - scorea;
        }
    });

    $('form[role="search"] input.search-query').typeahead({
        sections: [{
            source: data,
            highlight: true
        }]
    }).on('typeahead:selected',function(e,obj) {
        window.location.href = obj.url;
    });
})();