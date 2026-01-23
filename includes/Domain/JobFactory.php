<?php

namespace BusinessApp\Domain;

class JobFactory
{
    public function createFromAcceptedQuote(Quote $quote)
    {
        if ($quote->getStatus() !== 'accepted') {
            throw new \RuntimeException('Only accepted quotes can be converted to jobs');
        }

        return new Job(null, $quote->getId(), 'pending');
    }
}

