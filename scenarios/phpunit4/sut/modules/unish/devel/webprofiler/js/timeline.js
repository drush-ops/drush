/**
 * @file
 * Timeline panel app.
 */
(function ($, Drupal, drupalSettings, d3) {

  'use strict';

  Drupal.behaviors.webprofiler_timeline = {
    attach: function (context) {
      if (typeof d3 != 'undefined') {

        // data
        var data = drupalSettings.webprofiler.time.events;
        var parts = [];
        var dataL = data.length;
        var perL;
        var labelW = [];
        var rowW;
        var scalePadding;
        var endTime = parseInt(data[(dataL - 1)].endtime);
        var roundTime = Math.ceil(endTime / 1000) * 1000;
        var endScale;

        for (var j = 0; j < dataL; j++) {
          perL = data[j].periods.length;
          for (var k = 0; k < perL; k++) {
            parts.push({
              lane: j,
              category: data[j].category,
              memory: data[j].memory,
              name: data[j].name,
              start: data[j].periods[k].start,
              end: data[j].periods[k].end
            });
          }
        }

        var tooltipCtrl = function (d, i) {
          tooltip.html('<span class="tooltip__content">memory usage: ' + d.memory + '</span>' +
          '<span class="tooltip__content">' + parseInt(d.start) + 'ms ~ ' + parseInt(d.end) + 'ms</span>');
          tooltip
            .style('display', 'block')
            .style('left', (d3.event.layerX - 87) + 'px')
            .style('top', ((d.lane + 1) * 22) + 'px')
            .style('opacity', .9);
        };

        var xscale = d3.scale.linear().domain([0, roundTime]).range([0, 1000]);
        d3.select('#timeline').append('svg').attr('height', (dataL + 1) * 22 + 'px').attr('width', '100%').attr('class', 'timeline__canvas');

        // tooltips
        var tooltip = d3.select('#timeline')
          .append('div')
          .attr('class', 'tooltip');


        // Add a rectangle for every data element.
        d3.select('.timeline__canvas')
            .append('g')
            .attr('class', 'timeline__rows')
            .attr('x', 0)
            .attr('y', 0)
            .selectAll('g')
            .data(data)
            .enter()
            .append('rect')
            .attr('class', 'timeline__row')
            .attr('x', 0)
            .attr('y', function (d, i) {
              return (i * 22);
            })
            .attr('height', 22)
            .attr('width', '100%')
            .each(function () {
              rowW = this.getBoundingClientRect().width;
            });

        // scale
        var scale = d3.select('.timeline__canvas')
          .append('g')
          .attr('class', 'timeline__scale')
          .attr('id', 'timeline__scale')
          .attr('x', 0)
          .attr('y', 0)
          .selectAll('g')
          .data(data)
          .enter()
          .append('a')
          .attr('xlink:href', function (d) {
            return Drupal.webprofiler.helpers.ideLink(d.link);
          })
          .attr('class', function (d) {
            return 'timeline__label ' + d.category;
          })
          .attr('x', xscale(5))
          .attr('y', function (d, i) {
            return (((i + 1) * 22) - 5);
          });

        scale.append('title')
          .text(function (d) {
            return d.name;
          });

        scale.append('text')
          .attr('x', xscale(5))
          .attr('y', function (d, i) {
            return (((i + 1) * 22) - 5);
          })
          .text(function (d) {
            return Drupal.webprofiler.helpers.shortLink(d.name);
          })
          .each(function (d) {
            labelW.push(this.getBoundingClientRect().width);
          });

        scalePadding = Math.max.apply(null, labelW) + 10;

        scale.insert('rect', 'title')
          .attr('x', 0)
          .attr('y', function (d, i) {
            return (i * 22);
          })
          .attr('height', 22)
          .attr('stroke', 'transparent')
          .attr('strokw-width', 1)
          .attr('width', scalePadding);

        // times
        var events = d3.select('.timeline__canvas')
          .insert('g', '.timeline__scale')
          .attr('class', 'timeline__parts')
          .attr('x', 0)
          .attr('y', 0)
          .selectAll('g')
          .data(parts)
          .enter();

        events.append('rect').attr('class', function (d) {
          return 'timeline__period--' + d.category;
        })
          .attr('x', function (d) {
            return xscale(parseInt(d.start)) + scalePadding;
          })
          .attr('y', function (d) {
            return d.lane * 22;
          })
          .attr('height', 22)
          .attr('width', function (d) {
            return xscale(Math.max(parseInt(d.end - d.start), 1));
          });

        events.append('rect')
          .attr('class', function (d) {
            return 'timeline__period-trigger';
          })
          .attr('x', function (d) {
            return xscale(parseInt(d.start)) + scalePadding - 5;
          })
          .attr('y', function (d) {
            return d.lane * 22;
          })
          .attr('height', 22)
          .attr('width', function (d) {
            return xscale(Math.max(parseInt(d.end - d.start), 1)) + 11;
          })
          .on('mouseover', function (d, i) {
            tooltipCtrl(d, i);
          })
          .on('mouseout', function (d) {
            tooltip
            .style('display', 'none');
          });

        // Draw X-axis grid lines
        d3.select('.timeline__parts').insert('g', '.timeline__parts')
          .selectAll('line')
          .data(xscale.ticks(10))
          .enter()
          .append('line')
          .attr('class', 'timeline__scale--x')
          .attr('x1', xscale)
          .attr('x2', xscale)
          .attr('y1', 0)
          .attr('y2', data.length * 22)
          .attr('transform', 'translate( ' + scalePadding + ' , 0)');

        var xAxis = d3.svg.axis().scale(xscale).ticks(10).orient('bottom').tickFormat(function (d) {
          return d + ' ms';
        });

        d3.select('.timeline__parts').insert('g', '.timeline__parts')
          .attr('class', 'axis')
          .attr('transform', 'translate(' + scalePadding + ', ' + dataL * 22 + ')')
          .call(xAxis);

        endScale = xscale(endTime) - rowW - parseInt(scalePadding);

        if (parseInt(xscale(endTime)) > (rowW - parseInt(scalePadding))) {
          d3.select('.timeline__canvas')
            .call(
            d3.behavior.zoom()
                .scaleExtent([1, 1])
                .x(xscale)
                .on('zoom', function () {

                  var t = d3.event.translate;
                  var tx = t[0];

                  tx = tx > 0 ? 0 : tx;

                  tx = tx < endScale ? endScale : tx;

                  d3.select('.timeline__parts').attr('transform', 'translate( ' + tx + ' , 0)');
                }));
        }

      }
    }
  };

})(jQuery, Drupal, drupalSettings, d3);
