<?php

namespace Vireo\Framework\Database\ORM;

/**
 * Spatial Query Builder
 *
 * Extends QueryBuilder with PostGIS spatial query capabilities
 * Supports geometric operations, distance calculations, and spatial predicates
 */
class SpatialQueryBuilder extends QueryBuilder
{
    /**
     * Default SRID (Spatial Reference System Identifier)
     * 4326 = WGS 84 (standard GPS coordinates)
     */
    protected int $defaultSRID = 4326;

    /**
     * Add a distance calculation to the select
     *
     * Calculates the distance between a spatial column and a point
     *
     * @param string $column The spatial column name
     * @param string $point WKT point format: "POINT(longitude latitude)"
     * @param string $as Alias for the distance column
     * @param int|null $srid Spatial reference system ID
     * @return static
     *
     * @example
     * $stores = query()->table('stores')
     *     ->distance('location', 'POINT(-122.4194 37.7749)', 'distance_m')
     *     ->get();
     */
    public function distance(string $column, string $point, string $as = 'distance', ?int $srid = null): static
    {
        $srid = $srid ?? $this->defaultSRID;
        $sql = "ST_Distance({$this->grammar->wrap($column)}, ST_GeomFromText(?, {$srid})) AS {$this->grammar->wrap($as)}";

        $this->components['selectRaw'][] = $sql;
        $this->bindings[] = $point;

        return $this;
    }

    /**
     * Add a distance calculation in meters (for geography type)
     *
     * @param string $column The spatial column name
     * @param string $point WKT point format
     * @param string $as Alias for the distance column
     * @return static
     */
    public function distanceInMeters(string $column, string $point, string $as = 'distance_meters'): static
    {
        $sql = "ST_Distance({$this->grammar->wrap($column)}::geography, ST_GeogFromText(?)) AS {$this->grammar->wrap($as)}";

        $this->components['selectRaw'][] = $sql;
        $this->bindings[] = $point;

        return $this;
    }

    /**
     * Filter results within a radius (in meters)
     *
     * Uses ST_DWithin for efficient radius searches with spatial indexes
     *
     * @param string $column The spatial column name
     * @param string $point WKT point format
     * @param float $distance Distance in meters
     * @param int|null $srid Spatial reference system ID
     * @return static
     *
     * @example
     * // Find stores within 5km of San Francisco
     * $stores = query()->table('stores')
     *     ->within('location', 'POINT(-122.4194 37.7749)', 5000)
     *     ->get();
     */
    public function within(string $column, string $point, float $distance, ?int $srid = null): static
    {
        $srid = $srid ?? $this->defaultSRID;
        $sql = "ST_DWithin({$this->grammar->wrap($column)}, ST_GeomFromText(?, {$srid}), ?)";

        $this->whereRaw($sql, [$point, $distance]);

        return $this;
    }

    /**
     * Filter by geometric intersection
     *
     * @param string $column The spatial column name
     * @param string $geometry WKT geometry format
     * @param int|null $srid Spatial reference system ID
     * @return static
     *
     * @example
     * // Find stores that intersect with a polygon
     * $stores = query()->table('stores')
     *     ->intersects('delivery_zone', 'POLYGON((...coordinates...))')
     *     ->get();
     */
    public function intersects(string $column, string $geometry, ?int $srid = null): static
    {
        $srid = $srid ?? $this->defaultSRID;
        $sql = "ST_Intersects({$this->grammar->wrap($column)}, ST_GeomFromText(?, {$srid}))";

        $this->whereRaw($sql, [$geometry]);

        return $this;
    }

    /**
     * Filter by geometric containment (point within polygon)
     *
     * @param string $column The spatial column name (usually polygon)
     * @param string $geometry WKT geometry format (usually point)
     * @param int|null $srid Spatial reference system ID
     * @return static
     *
     * @example
     * // Check if user location is within store delivery zones
     * $canDeliver = query()->table('stores')
     *     ->where('id', $storeId)
     *     ->contains('delivery_zone', 'POINT(-122.4194 37.7749)')
     *     ->exists();
     */
    public function contains(string $column, string $geometry, ?int $srid = null): static
    {
        $srid = $srid ?? $this->defaultSRID;
        $sql = "ST_Contains({$this->grammar->wrap($column)}, ST_GeomFromText(?, {$srid}))";

        $this->whereRaw($sql, [$geometry]);

        return $this;
    }

    /**
     * Filter by geometric "within" (inverse of contains)
     *
     * @param string $column The spatial column name
     * @param string $geometry WKT geometry format
     * @param int|null $srid Spatial reference system ID
     * @return static
     */
    public function containedBy(string $column, string $geometry, ?int $srid = null): static
    {
        $srid = $srid ?? $this->defaultSRID;
        $sql = "ST_Within({$this->grammar->wrap($column)}, ST_GeomFromText(?, {$srid}))";

        $this->whereRaw($sql, [$geometry]);

        return $this;
    }

    /**
     * Filter by geometric crosses
     *
     * @param string $column The spatial column name
     * @param string $geometry WKT geometry format
     * @param int|null $srid Spatial reference system ID
     * @return static
     */
    public function crosses(string $column, string $geometry, ?int $srid = null): static
    {
        $srid = $srid ?? $this->defaultSRID;
        $sql = "ST_Crosses({$this->grammar->wrap($column)}, ST_GeomFromText(?, {$srid}))";

        $this->whereRaw($sql, [$geometry]);

        return $this;
    }

    /**
     * Filter by geometric touches
     *
     * @param string $column The spatial column name
     * @param string $geometry WKT geometry format
     * @param int|null $srid Spatial reference system ID
     * @return static
     */
    public function touches(string $column, string $geometry, ?int $srid = null): static
    {
        $srid = $srid ?? $this->defaultSRID;
        $sql = "ST_Touches({$this->grammar->wrap($column)}, ST_GeomFromText(?, {$srid}))";

        $this->whereRaw($sql, [$geometry]);

        return $this;
    }

    /**
     * Filter by geometric overlaps
     *
     * @param string $column The spatial column name
     * @param string $geometry WKT geometry format
     * @param int|null $srid Spatial reference system ID
     * @return static
     */
    public function overlaps(string $column, string $geometry, ?int $srid = null): static
    {
        $srid = $srid ?? $this->defaultSRID;
        $sql = "ST_Overlaps({$this->grammar->wrap($column)}, ST_GeomFromText(?, {$srid}))";

        $this->whereRaw($sql, [$geometry]);

        return $this;
    }

    /**
     * Filter by geometric disjoint (does not intersect)
     *
     * @param string $column The spatial column name
     * @param string $geometry WKT geometry format
     * @param int|null $srid Spatial reference system ID
     * @return static
     */
    public function disjoint(string $column, string $geometry, ?int $srid = null): static
    {
        $srid = $srid ?? $this->defaultSRID;
        $sql = "ST_Disjoint({$this->grammar->wrap($column)}, ST_GeomFromText(?, {$srid}))";

        $this->whereRaw($sql, [$geometry]);

        return $this;
    }

    /**
     * Transform spatial column to different SRID
     *
     * @param string $column The spatial column name
     * @param int $targetSRID Target SRID
     * @param string $as Alias for the transformed column
     * @return static
     *
     * @example
     * // Transform from WGS84 (4326) to Web Mercator (3857)
     * $query->transform('location', 3857, 'location_mercator');
     */
    public function transformTo(string $column, int $targetSRID, string $as): static
    {
        $sql = "ST_Transform({$this->grammar->wrap($column)}, {$targetSRID}) AS {$this->grammar->wrap($as)}";

        $this->components['selectRaw'][] = $sql;

        return $this;
    }

    /**
     * Get geometry as GeoJSON
     *
     * @param string $column The spatial column name
     * @param string $as Alias for the GeoJSON column
     * @param int|null $precision Decimal precision
     * @return static
     *
     * @example
     * $stores = query()->table('stores')
     *     ->asGeoJSON('location', 'location_geojson')
     *     ->get();
     */
    public function asGeoJSON(string $column, string $as = 'geojson', ?int $precision = null): static
    {
        if ($precision !== null) {
            $sql = "ST_AsGeoJSON({$this->grammar->wrap($column)}, {$precision}) AS {$this->grammar->wrap($as)}";
        } else {
            $sql = "ST_AsGeoJSON({$this->grammar->wrap($column)}) AS {$this->grammar->wrap($as)}";
        }

        $this->components['selectRaw'][] = $sql;

        return $this;
    }

    /**
     * Get geometry as WKT (Well-Known Text)
     *
     * @param string $column The spatial column name
     * @param string $as Alias for the WKT column
     * @return static
     */
    public function asText(string $column, string $as = 'wkt'): static
    {
        $sql = "ST_AsText({$this->grammar->wrap($column)}) AS {$this->grammar->wrap($as)}";

        $this->components['selectRaw'][] = $sql;

        return $this;
    }

    /**
     * Get geometry as WKB (Well-Known Binary)
     *
     * @param string $column The spatial column name
     * @param string $as Alias for the WKB column
     * @return static
     */
    public function asBinary(string $column, string $as = 'wkb'): static
    {
        $sql = "ST_AsBinary({$this->grammar->wrap($column)}) AS {$this->grammar->wrap($as)}";

        $this->components['selectRaw'][] = $sql;

        return $this;
    }

    /**
     * Get the area of a geometry
     *
     * @param string $column The spatial column name
     * @param string $as Alias for the area column
     * @param bool $useSpheroid Use spheroid calculation (more accurate)
     * @return static
     *
     * @example
     * $polygons = query()->table('zones')
     *     ->area('boundary', 'area_sq_meters')
     *     ->get();
     */
    public function area(string $column, string $as = 'area', bool $useSpheroid = true): static
    {
        if ($useSpheroid) {
            $sql = "ST_Area({$this->grammar->wrap($column)}::geography) AS {$this->grammar->wrap($as)}";
        } else {
            $sql = "ST_Area({$this->grammar->wrap($column)}) AS {$this->grammar->wrap($as)}";
        }

        $this->components['selectRaw'][] = $sql;

        return $this;
    }

    /**
     * Get the length of a linestring
     *
     * @param string $column The spatial column name
     * @param string $as Alias for the length column
     * @param bool $useSpheroid Use spheroid calculation (more accurate)
     * @return static
     */
    public function length(string $column, string $as = 'length', bool $useSpheroid = true): static
    {
        if ($useSpheroid) {
            $sql = "ST_Length({$this->grammar->wrap($column)}::geography) AS {$this->grammar->wrap($as)}";
        } else {
            $sql = "ST_Length({$this->grammar->wrap($column)}) AS {$this->grammar->wrap($as)}";
        }

        $this->components['selectRaw'][] = $sql;

        return $this;
    }

    /**
     * Get the perimeter of a polygon
     *
     * @param string $column The spatial column name
     * @param string $as Alias for the perimeter column
     * @return static
     */
    public function perimeter(string $column, string $as = 'perimeter'): static
    {
        $sql = "ST_Perimeter({$this->grammar->wrap($column)}) AS {$this->grammar->wrap($as)}";

        $this->components['selectRaw'][] = $sql;

        return $this;
    }

    /**
     * Get the centroid of a geometry
     *
     * @param string $column The spatial column name
     * @param string $as Alias for the centroid column
     * @return static
     */
    public function centroid(string $column, string $as = 'centroid'): static
    {
        $sql = "ST_Centroid({$this->grammar->wrap($column)}) AS {$this->grammar->wrap($as)}";

        $this->components['selectRaw'][] = $sql;

        return $this;
    }

    /**
     * Create a buffer around a geometry
     *
     * @param string $column The spatial column name
     * @param float $radius Buffer radius
     * @param string $as Alias for the buffered geometry
     * @return static
     *
     * @example
     * // Create 1km buffer around points
     * $buffered = query()->table('points')
     *     ->buffer('location', 1000, 'buffer_zone')
     *     ->get();
     */
    public function buffer(string $column, float $radius, string $as = 'buffer'): static
    {
        $sql = "ST_Buffer({$this->grammar->wrap($column)}::geography, {$radius}) AS {$this->grammar->wrap($as)}";

        $this->components['selectRaw'][] = $sql;

        return $this;
    }

    /**
     * Order results by distance from a point
     *
     * @param string $column The spatial column name
     * @param string $point WKT point format
     * @param string $direction Sort direction (asc or desc)
     * @param int|null $srid Spatial reference system ID
     * @return static
     *
     * @example
     * // Get nearest stores
     * $stores = query()->table('stores')
     *     ->orderByDistance('location', 'POINT(-122.4194 37.7749)')
     *     ->limit(10)
     *     ->get();
     */
    public function orderByDistance(string $column, string $point, string $direction = 'asc', ?int $srid = null): static
    {
        $srid = $srid ?? $this->defaultSRID;

        // Add distance to bindings for ORDER BY
        $this->components['orders'][] = [
            'type' => 'spatial',
            'column' => $column,
            'point' => $point,
            'srid' => $srid,
            'direction' => $direction,
        ];

        $this->bindings[] = $point;

        return $this;
    }

    /**
     * Set the default SRID for spatial operations
     *
     * @param int $srid Spatial reference system ID
     * @return static
     */
    public function setSRID(int $srid): static
    {
        $this->defaultSRID = $srid;
        return $this;
    }

    /**
     * Get the current default SRID
     *
     * @return int
     */
    public function getSRID(): int
    {
        return $this->defaultSRID;
    }
}
