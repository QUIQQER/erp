<?php

namespace QUITests\ERP\Output;

class ProjectAwareEntityFixture
{
    public function getAttribute(string $name): mixed
    {
        return $name === 'project_name' ? 'entity-project' : null;
    }

    public function getCustomer(): ProjectAwareCustomerFixture
    {
        return new ProjectAwareCustomerFixture();
    }
}
