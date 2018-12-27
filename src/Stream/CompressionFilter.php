<?php
namespace PdfBuilder\Stream;

class CompressionFilter
{
    /**
     * @var array - Array of active filters, keyed by object hash
     */
    protected $activeFilters = [];

    /**
     * Add a zlib deflate filter.
     *
     * @param resource $resource
     */
    public function addDeflateWriter($resource)
    {
        $id = $this->getResourceId($resource);
        $this->removeFilter($resource);

        $this->activeFilters[$id] = stream_filter_append(
            $resource,
            'zlib.deflate',
            STREAM_FILTER_WRITE,
            [
                'level'  => 6,
                'window' => 8,
                'memory' => 1,
            ]
        );
    }

    /**
     * Add a zlib inflate filter to a stream.
     *
     * @param    $resource
     * @internal param resource $stream
     */
    public function addDeflateReader($resource)
    {
        $this->removeFilter($resource);
        $id = $this->getResourceId($resource);

        $this->activeFilters[$id] = stream_filter_append(
            $resource,
            'zlib.inflate',
            STREAM_FILTER_READ,
            [
                'level'  => 6,
                'window' => 8,
                'memory' => 1,
            ]
        );
    }

    /**
     * Remove filter from object.
     *
     * @param resource $resource
     */
    public function removeFilter($resource)
    {
        $id = $this->getResourceId($resource);
        $resource = (isset($this->activeFilters[$id]) ? $this->activeFilters[$id] : false);

        if (is_resource($resource)) {
            stream_filter_remove($resource);
            unset($this->activeFilters[$id]);
        }
    }

    /**
     * Get string Id from resource
     *
     * @param  $resource
     * @return bool|mixed
     */
    public function getResourceId($resource) {
        if (!is_resource($resource)) {
            return false;
        }

        $res = explode('#', (string)$resource);
        return array_pop($res);
    }
}
